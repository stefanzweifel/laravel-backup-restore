<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Wnx\LaravelBackupRestore\Actions\CheckDependenciesAction;
use Wnx\LaravelBackupRestore\Databases\DbImporter;
use Wnx\LaravelBackupRestore\DbImporterFactory;
use Wnx\LaravelBackupRestore\Exceptions\CliNotFound;

/**
 * A directory holding a file that the action will accept as the given binary.
 * Windows resolves "mysql" to "mysql.exe"; everywhere else the file needs the
 * executable bit.
 */
function lbrDirectoryWithBinary(string $name): string
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lbr-binary-'.uniqid();

    mkdir($directory);

    $binary = $directory.DIRECTORY_SEPARATOR.$name.(windows_os() ? '.exe' : '');

    touch($binary);
    chmod($binary, 0755);

    return $directory;
}

it('does not throw exception for supported database drivers', function ($connection) {
    app(CheckDependenciesAction::class)->execute($connection);
    $this->assertTrue(true);
})->with(['mysql', 'pgsql', 'sqlite']);

it('checks no binary for sqlite, which imports through PDO', function () {
    config()->set('backup-restore.import_binary_path', '/does/not/exist/');

    app(CheckDependenciesAction::class)->execute('sqlite');

    $this->assertTrue(true);
});

it('passes when the configured path holds the binary', function () {
    config()->set('backup-restore.import_binary_path', lbrDirectoryWithBinary('mysql'));

    app(CheckDependenciesAction::class)->execute('mysql');

    $this->assertTrue(true);
});

it('checks the binary at the configured import_binary_path', function () {
    config()->set('backup-restore.import_binary_path', '/does/not/exist/');

    app(CheckDependenciesAction::class)->execute('mysql');
})->throws(CliNotFound::class, '/does/not/exist/mysql');

it('reads a path configured for one connection', function () {
    config()->set('backup-restore.import_binary_path', [
        'pgsql' => '/does/not/exist/',
    ]);

    app(CheckDependenciesAction::class)->execute('mysql');

    app(CheckDependenciesAction::class)->execute('pgsql');
})->throws(CliNotFound::class, '/does/not/exist/psql');

it('still reads the deprecated dump_binary_path', function () {
    config()->set('database.connections.mysql.dump.dump_binary_path', '/does/not/exist/');

    app(CheckDependenciesAction::class)->execute('mysql');
})->throws(CliNotFound::class, '/does/not/exist/mysql');

it('prefers import_binary_path over the deprecated dump_binary_path', function () {
    config()->set('backup-restore.import_binary_path', '/from/backup-restore/');
    config()->set('database.connections.mysql.dump.dump_binary_path', '/from/database/');

    app(CheckDependenciesAction::class)->execute('mysql');
})->throws(CliNotFound::class, '/from/backup-restore/mysql');

it('checks pg_restore as well, because a custom-format dump needs it', function () {
    config()->set('backup-restore.import_binary_path', lbrDirectoryWithBinary('psql'));

    app(CheckDependenciesAction::class)->execute('pgsql');
})->throws(CliNotFound::class, 'pg_restore');

it('throws exception if CLI dependency for given connection can not be found', function () {
    DbImporterFactory::extend('sqlsrv', new class extends DbImporter
    {
        public function getImportCommand(string $dumpFile, string $connection): string
        {
            return '';
        }

        public function getCliName(): string
        {
            return 'not-existing-cli';
        }
    });

    app(CheckDependenciesAction::class)->execute('unsupported-driver');

})
    ->expectExceptionMessage('The "not-existing-cli" binary was not found.')
    ->expectException(CliNotFound::class);

it('looks up CLI dependencies with the lookup command of the current platform', function () {
    Process::fake();

    app(CheckDependenciesAction::class)->execute('mysql');

    $lookup = windows_os() ? 'where' : 'which';

    // Only the database client is looked up. Compressed dumps are decompressed
    // in PHP, so there is no gzip or bunzip2 to find.
    Process::assertRan(fn (PendingProcess $process) => $process->command === [$lookup, 'mysql']);
});

it('asks the filesystem rather than the platform lookup for a configured path', function () {
    Process::fake();

    config()->set('backup-restore.import_binary_path', '/does/not/exist/');

    expect(fn () => app(CheckDependenciesAction::class)->execute('mysql'))
        ->toThrow(CliNotFound::class);

    Process::assertNothingRan();
});
