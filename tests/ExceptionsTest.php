<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Exceptions\BackupRestoreException;
use Wnx\LaravelBackupRestore\Exceptions\CannotCreateDbImporter;
use Wnx\LaravelBackupRestore\Exceptions\CliNotFound;
use Wnx\LaravelBackupRestore\Exceptions\DecompressionFailed;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;
use Wnx\LaravelBackupRestore\Exceptions\NoBackupsFound;
use Wnx\LaravelBackupRestore\Exceptions\NoDatabaseDumpsFound;
use Wnx\LaravelBackupRestore\PendingRestore;

function pendingRestoreForException(): PendingRestore
{
    return PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/only-files.zip',
        connection: 'sqlite-restore',
        backupPassword: null,
    );
}

it('exposes every exception through the BackupRestoreException interface', function (Throwable $exception) {
    expect($exception)->toBeInstanceOf(BackupRestoreException::class)
        ->and($exception)->toBeInstanceOf(Exception::class);
})->with([
    fn () => NoDatabaseDumpsFound::notFoundInBackup(pendingRestoreForException()),
    fn () => NoBackupsFound::onDisk('s3'),
    fn () => CannotCreateDbImporter::configNotFound('foo'),
    fn () => CannotCreateDbImporter::unsupportedDriver('sqlsrv'),
    fn () => CliNotFound::create('psql'),
    fn () => DecompressionFailed::create(ZipArchive::ER_NOZIP, '/tmp/backup.zip'),
    fn () => DecompressionFailed::pathTraversalDetected('../evil.sql', '/tmp/backup.zip'),
    fn () => ImportFailed::decompressionFailed('dump.sql.xz', 'Unknown compression format'),
]);

it('keeps every exception message on a single line', function (Throwable $exception) {
    expect($exception->getMessage())
        ->not->toContain("\n")
        ->not->toContain('"No ');
})->with([
    fn () => NoDatabaseDumpsFound::notFoundInBackup(pendingRestoreForException()),
    fn () => NoBackupsFound::onDisk('s3'),
    fn () => CannotCreateDbImporter::configNotFound('foo'),
    fn () => CannotCreateDbImporter::unsupportedDriver('sqlsrv'),
    fn () => CliNotFound::create('psql'),
    fn () => DecompressionFailed::create(ZipArchive::ER_NOZIP, '/tmp/backup.zip'),
    fn () => DecompressionFailed::pathTraversalDetected('../evil.sql', '/tmp/backup.zip'),
    fn () => ImportFailed::decompressionFailed('dump.sql.xz', 'Unknown compression format'),
]);

it('carries the backup and the files found for NoDatabaseDumpsFound', function () {
    $pendingRestore = pendingRestoreForException();

    Storage::disk('local')->put(
        $pendingRestore->getPathToLocalDecompressedBackup().'/db-dumps/not-a-sql-file.txt',
        'nope'
    );

    $exception = NoDatabaseDumpsFound::notFoundInBackup($pendingRestore);

    expect($exception->backup)->toBe('Laravel/only-files.zip')
        ->and($exception->filesInBackup)->toHaveCount(1)
        ->and($exception->filesInBackup[0])->toContain('not-a-sql-file.txt')
        ->and($exception->getMessage())->toBe('The backup "Laravel/only-files.zip" contains no database dumps.')
        ->and($exception->hint())->toContain('not-a-sql-file.txt');
});

it('says the db-dumps directory is empty when no files were found', function () {
    $exception = NoDatabaseDumpsFound::notFoundInBackup(pendingRestoreForException());

    expect($exception->filesInBackup)->toBe([])
        ->and($exception->hint())->toContain('empty');
});

it('carries the disk and the backup name for NoBackupsFound', function () {
    config(['backup.backup.name' => 'Laravel']);

    $exception = NoBackupsFound::onDisk('s3');

    expect($exception->disk)->toBe('s3')
        ->and($exception->backupName)->toBe('Laravel')
        ->and($exception->getMessage())->toBe('No backups found on disk "s3".')
        ->and($exception->hint())->toContain('Laravel');
});

it('carries the connection name for CannotCreateDbImporter', function () {
    $exception = CannotCreateDbImporter::configNotFound('foo');

    expect($exception->connectionName)->toBe('foo')
        ->and($exception->driver)->toBeNull()
        ->and($exception->getMessage())->toBe('Database connection "foo" is not configured.')
        ->and($exception->hint())->toContain('config/database.php');
});

it('carries the driver for CannotCreateDbImporter', function () {
    $exception = CannotCreateDbImporter::unsupportedDriver('sqlsrv');

    expect($exception->driver)->toBe('sqlsrv')
        ->and($exception->connectionName)->toBeNull()
        ->and($exception->getMessage())->toBe('Database driver "sqlsrv" is not supported.')
        ->and($exception->hint())->toContain('DbImporterFactory::extend()');
});

it('carries the cli name for CliNotFound', function () {
    $exception = CliNotFound::create('psql');

    expect($exception->cli)->toBe('psql')
        ->and($exception->getMessage())->toBe('The "psql" binary was not found.')
        ->and($exception->hint())->toContain('PATH');
});

it('carries the archive and the zip error code for DecompressionFailed', function () {
    $exception = DecompressionFailed::create(ZipArchive::ER_NOZIP, '/tmp/backup.zip');

    expect($exception->archive)->toBe('/tmp/backup.zip')
        ->and($exception->errorCode)->toBe(ZipArchive::ER_NOZIP)
        ->and($exception->entryName)->toBeNull()
        ->and($exception->getMessage())->toContain('Not a zip archive. (ZipArchive::ER_NOZIP)')
        ->and($exception->getMessage())->toContain('/tmp/backup.zip');
});

it('hints at the password when the archive could not be extracted', function () {
    $exception = DecompressionFailed::create(false, '/tmp/backup.zip');

    expect($exception->hint())->toContain('--password');
});

it('carries the offending entry name for a path traversal', function () {
    $exception = DecompressionFailed::pathTraversalDetected('../evil.sql', '/tmp/backup.zip');

    expect($exception->entryName)->toBe('../evil.sql')
        ->and($exception->archive)->toBe('/tmp/backup.zip')
        ->and($exception->getMessage())->toContain('path traversal');
});

it('carries the exit code and the process output for ImportFailed', function () {
    Process::fake([
        '*' => Process::result(output: 'some output', errorOutput: 'some error output', exitCode: 3),
    ]);

    $result = Process::run('whatever');

    $exception = ImportFailed::processDidNotEndSuccessfully($result, '/tmp/dump.sql');

    expect($exception->exitCode)->toBe(3)
        ->and($exception->output)->toContain('some output')
        ->and($exception->errorOutput)->toContain('some error output')
        ->and($exception->dumpFile)->toBe('/tmp/dump.sql')
        ->and($exception->getMessage())->toBe('The import of "/tmp/dump.sql" failed with exit code 3.')
        ->and($exception->hint())->toContain('some error output');
});

it('truncates captured process output to the last 4 KB', function () {
    $long = str_repeat('a', 10000);

    Process::fake([
        '*' => Process::result(output: $long, errorOutput: $long, exitCode: 1),
    ]);

    $exception = ImportFailed::processDidNotEndSuccessfully(Process::run('whatever'), 'dump.sql');

    expect(strlen($exception->output))->toBeLessThanOrEqual(4096)
        ->and(strlen($exception->errorOutput))->toBeLessThanOrEqual(4096);
});

it('keeps only the last 20 lines of stderr in the ImportFailed hint', function () {
    $errorOutput = collect(range(1, 50))->map(fn (int $i) => "line $i")->implode("\n");

    Process::fake([
        '*' => Process::result(output: '', errorOutput: $errorOutput, exitCode: 1),
    ]);

    $exception = ImportFailed::processDidNotEndSuccessfully(Process::run('whatever'), 'dump.sql');

    expect($exception->hint())->toContain('line 50')
        ->and($exception->hint())->toContain('line 31')
        ->and($exception->hint())->not->toContain('line 30');
});
