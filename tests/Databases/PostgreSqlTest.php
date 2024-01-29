<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Wnx\LaravelBackupRestore\Databases\PostgreSql;
use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

it('imports pgsql dump', function (string $dumpFile) {
    Event::fake();

    app(PostgreSql::class)->importToDatabase($dumpFile, 'pgsql');

    Event::assertDispatched(function (DatabaseDumpImportWasSuccessful $event) use ($dumpFile) {
        return $event->absolutePathToDump === $dumpFile;
    });

    $result = DB::connection('pgsql')->table('users')->count();
    expect($result)->toBe(10);
})->with([
    __DIR__.'/../storage/Laravel/2023-03-04-pgsql-no-compression-no-encryption.sql',
    __DIR__.'/../storage/Laravel/2023-03-04-pgsql-compression-no-encryption.sql.gz',
    __DIR__.'/../storage/Laravel/2023-03-04-pgsql-compression-no-encryption.sql.bz2',
])->group('pgsql');

it('uses default binary to import pgsql dump', function () {
    Event::fake();
    Process::fake();

    $dumpFile = __DIR__.'/../storage/Laravel/2023-03-04-pgsql-no-compression-no-encryption.sql';

    app(PostgreSql::class)->importToDatabase($dumpFile, 'pgsql');

    Process::assertRan(function (PendingProcess $process) {
        assertStringNotContainsString('/usr/bin/psql', $process->command);
        assertStringContainsString('psql', $process->command);

        return true;
    });
    Event::assertDispatched(function (DatabaseDumpImportWasSuccessful $event) use ($dumpFile) {
        return $event->absolutePathToDump === $dumpFile;
    });

    $result = DB::connection('pgsql')->table('users')->count();
    expect($result)->toBe(10);
})->group('pgsql');

it('uses custom binary to import pgsql dump', function () {
    Event::fake();
    Process::fake();

    $dumpFile = __DIR__.'/../storage/Laravel/2023-03-04-pgsql-no-compression-no-encryption.sql';

    app(PostgreSql::class)->importToDatabase($dumpFile, 'pgsql-restore-binary-path');

    Process::assertRan(function (PendingProcess $process) {
        assertStringContainsString('/usr/bin/psql', $process->command);

        return true;
    });
    Event::assertDispatched(function (DatabaseDumpImportWasSuccessful $event) use ($dumpFile) {
        return $event->absolutePathToDump === $dumpFile;
    });

    $result = DB::connection('pgsql')->table('users')->count();
    expect($result)->toBe(10);
})->group('pgsql');

it('uses custom binary to import compressed pgsql dump', function () {
    Event::fake();
    Process::fake();

    $dumpFile = __DIR__.'/../storage/Laravel/2023-03-04-pgsql-compression-no-encryption.sql.gz';

    app(PostgreSql::class)->importToDatabase($dumpFile, 'pgsql-restore-binary-path');

    Process::assertRan(function (PendingProcess $process) {
        assertStringContainsString('gunzip -c', $process->command);
        assertStringContainsString('/usr/bin/psql', $process->command);

        return true;
    });
    Event::assertDispatched(function (DatabaseDumpImportWasSuccessful $event) use ($dumpFile) {
        return $event->absolutePathToDump === $dumpFile;
    });

    $result = DB::connection('pgsql')->table('users')->count();
    expect($result)->toBe(10);
})->group('pgsql');

it('throws import failed exception if pgsql dump could not be imported')
    ->tap(fn () => app(PostgreSql::class)->importToDatabase('file-does-not-exist', 'pgsql'))
    ->throws(ImportFailed::class)
    ->group('pgsql');
