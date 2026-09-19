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
})->group('pgsql');

it('throws import failed exception if pgsql dump could not be imported')
    ->tap(fn () => app(PostgreSql::class)->importToDatabase('file-does-not-exist', 'pgsql'))
    ->throws(ImportFailed::class)
    ->group('pgsql');

it('shell-escapes the dump path in the uncompressed pgsql import command', function () {
    $maliciousPath = '/tmp/backup.sql;touch /tmp/lbr_security_test;#.sql';

    $command = app(PostgreSql::class)->getImportCommand($maliciousPath, 'pgsql');

    expect($command)->toContain(escapeshellarg($maliciousPath));
})->group('pgsql');

it('shell-escapes the dump path in the compressed gz pgsql import command', function () {
    $maliciousPath = '/tmp/backup.sql;touch /tmp/lbr_security_test;#.sql.gz';

    $command = app(PostgreSql::class)->getImportCommand($maliciousPath, 'pgsql');

    expect($command)->toContain(escapeshellarg($maliciousPath));
})->group('pgsql');

it('shell-escapes the dump path in the compressed bz2 pgsql import command', function () {
    $maliciousPath = '/tmp/backup.sql;touch /tmp/lbr_security_test;#.sql.bz2';

    $command = app(PostgreSql::class)->getImportCommand($maliciousPath, 'pgsql');

    expect($command)->toContain(escapeshellarg($maliciousPath));
})->group('pgsql');

// A connection whose every parameter carries a shell payload. It is not one of the
// connections the test suite wipes, so it never has to be reachable.
$injectionConnection = function (): string {
    config()->set('database.connections.pgsql-injection', [
        'driver' => 'pgsql',
        'host' => '127.0.0.1 $(touch /tmp/lbr_security_test)',
        'port' => '5432;touch /tmp/lbr_security_test',
        'database' => 'database`touch /tmp/lbr_security_test`',
        'username' => 'root;touch /tmp/lbr_security_test',
        'password' => "secret';touch /tmp/lbr_security_test;'",
        'search_path' => 'public',
    ]);

    return 'pgsql-injection';
};

$expectedInjectionDsn = 'postgresql://root%3Btouch%20%2Ftmp%2Flbr_security_test'.
    ':secret%27%3Btouch%20%2Ftmp%2Flbr_security_test%3B%27'.
    '@127.0.0.1 $(touch /tmp/lbr_security_test)'.
    ':5432;touch /tmp/lbr_security_test'.
    '/database%60touch%20%2Ftmp%2Flbr_security_test%60';

it('shell-escapes connection credentials in the uncompressed pgsql import command', function () use ($injectionConnection, $expectedInjectionDsn) {
    $command = app(PostgreSql::class)->getImportCommand('/tmp/backup.sql', $injectionConnection());

    expect($command)->toContain(escapeshellarg($expectedInjectionDsn));
})->group('pgsql');

it('shell-escapes connection credentials in the compressed pgsql import command', function () use ($injectionConnection, $expectedInjectionDsn) {
    $command = app(PostgreSql::class)->getImportCommand('/tmp/backup.sql.gz', $injectionConnection());

    expect($command)->toContain(escapeshellarg($expectedInjectionDsn));
})->group('pgsql');

it('shell-escapes connection credentials in the binary pgsql import command', function () use ($injectionConnection) {
    $command = app(PostgreSql::class)->getImportCommand('/tmp/backup.backup', $injectionConnection());

    expect($command)
        ->toContain('--host='.escapeshellarg('127.0.0.1 $(touch /tmp/lbr_security_test)'))
        ->toContain('--dbname='.escapeshellarg('database`touch /tmp/lbr_security_test`'));
})->group('pgsql');

it('shell-escapes the configured binary path in the pgsql import command', function () {
    config()->set('database.connections.pgsql-restore.dump.dump_binary_path', '/usr/bin/;touch /tmp/lbr_security_test');

    $command = app(PostgreSql::class)->getImportCommand('/tmp/backup.sql', 'pgsql-restore');

    expect($command)->toContain(escapeshellarg('/usr/bin/;touch /tmp/lbr_security_test/psql'));
})->group('pgsql');
