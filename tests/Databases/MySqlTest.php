<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Wnx\LaravelBackupRestore\Databases\MySql;
use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

use function PHPUnit\Framework\assertStringContainsString;

it('imports mysql dump', function (string $dumpFile) {
    Event::fake();

    app(MySql::class)->importToDatabase($dumpFile, 'mysql');

    Event::assertDispatched(function (DatabaseDumpImportWasSuccessful $event) use ($dumpFile) {
        return $event->absolutePathToDump === $dumpFile;
    });

    $result = DB::connection('mysql')->table('users')->count();
    expect($result)->toBe(10);
})->with([
    __DIR__.'/../storage/Laravel/2023-01-28-mysql-no-compression-no-encryption.sql',
    __DIR__.'/../storage/Laravel/2023-01-28-mysql-compression-no-encryption.sql.gz',
    __DIR__.'/../storage/Laravel/2023-01-28-mysql-compression-no-encryption.sql.bz2',
]);

it('uses default binary path to import mysql dump', function () {
    Event::fake();
    Process::fake();

    $dumpFile = __DIR__.'/../storage/Laravel/2023-01-28-mysql-no-compression-no-encryption.sql';

    app(MySql::class)->importToDatabase(
        dumpFile: $dumpFile,
        connection: 'mysql'
    );

    Process::assertRan(function (PendingProcess $process) {
        assertStringContainsString("'mysql'", $process->command);

        return true;
    });

    Event::assertDispatched(function (DatabaseDumpImportWasSuccessful $event) use ($dumpFile) {
        return $event->absolutePathToDump === $dumpFile;
    });
});

it('uses custom binary path to import mysql dump', function () {
    Event::fake();
    Process::fake();

    $dumpFile = __DIR__.'/../storage/Laravel/2023-01-28-mysql-no-compression-no-encryption.sql';

    app(MySql::class)->importToDatabase(
        dumpFile: $dumpFile,
        connection: 'mysql-restore-binary-path'
    );

    Process::assertRan(function (PendingProcess $process) {
        assertStringContainsString('/usr/bin/mysql', $process->command);

        return true;
    });

    Event::assertDispatched(function (DatabaseDumpImportWasSuccessful $event) use ($dumpFile) {
        return $event->absolutePathToDump === $dumpFile;
    });
});

it('uses custom binary path to import compressed mysql dump', function () {
    Event::fake();
    Process::fake();

    $dumpFile = __DIR__.'/../storage/Laravel/2023-01-28-mysql-compression-no-encryption.sql.gz';

    app(MySql::class)->importToDatabase(
        dumpFile: $dumpFile,
        connection: 'mysql-restore-binary-path'
    );

    Process::assertRan(function (PendingProcess $process) {
        assertStringContainsString('gunzip <', $process->command);
        assertStringContainsString('/usr/bin/mysql', $process->command);

        return true;
    });

    Event::assertDispatched(function (DatabaseDumpImportWasSuccessful $event) use ($dumpFile) {
        return $event->absolutePathToDump === $dumpFile;
    });
});

it('throws import failed exception if mysql dump could not be imported')
    ->tap(fn () => app(MySql::class)->importToDatabase('file-does-not-exist', 'mysql'))
    ->throws(ImportFailed::class);
