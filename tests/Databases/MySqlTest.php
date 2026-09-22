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

    app(MySql::class)->importToDatabase($dumpFile, 'mysql-restore');

    Event::assertDispatched(function (DatabaseDumpImportWasSuccessful $event) use ($dumpFile) {
        return $event->absolutePathToDump === $dumpFile;
    });

    $result = DB::connection('mysql-restore')->table('users')->count();
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
        connection: 'mysql-restore'
    );

    Process::assertRan(function (PendingProcess $process) {
        assertStringContainsString(escapeshellarg('mysql'), $process->command);

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
        assertStringContainsString('gzip -d -c', $process->command);
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

it('shell-escapes the dump path in the uncompressed mysql import command', function () {
    $maliciousPath = '/tmp/backup.sql;touch /tmp/lbr_security_test;#.sql';

    $command = app(MySql::class)->getImportCommand($maliciousPath, 'mysql-restore');

    expect($command)->toContain(escapeshellarg($maliciousPath));
});

it('shell-escapes the dump path in the compressed gz mysql import command', function () {
    $maliciousPath = '/tmp/backup.sql;touch /tmp/lbr_security_test;#.sql.gz';

    $command = app(MySql::class)->getImportCommand($maliciousPath, 'mysql-restore');

    expect($command)->toContain(escapeshellarg($maliciousPath));
});

it('shell-escapes the dump path in the compressed bz2 mysql import command', function () {
    $maliciousPath = '/tmp/backup.sql;touch /tmp/lbr_security_test;#.sql.bz2';

    $command = app(MySql::class)->getImportCommand($maliciousPath, 'mysql-restore');

    expect($command)->toContain(escapeshellarg($maliciousPath));
});

it('shell-escapes connection credentials in the uncompressed mysql import command', function () {
    config()->set('database.connections.mysql-restore.host', '127.0.0.1 $(touch /tmp/lbr_security_test)');
    config()->set('database.connections.mysql-restore.port', '3306;touch /tmp/lbr_security_test');
    config()->set('database.connections.mysql-restore.username', 'root;touch /tmp/lbr_security_test');
    config()->set('database.connections.mysql-restore.password', "secret';touch /tmp/lbr_security_test;'");
    config()->set('database.connections.mysql-restore.database', 'database`touch /tmp/lbr_security_test`');

    $command = app(MySql::class)->getImportCommand('/tmp/backup.sql', 'mysql-restore');

    expect($command)
        ->toContain('-u '.escapeshellarg('root;touch /tmp/lbr_security_test'))
        ->toContain('-p'.escapeshellarg("secret';touch /tmp/lbr_security_test;'"))
        ->toContain('-P '.escapeshellarg('3306;touch /tmp/lbr_security_test'))
        ->toContain('-h '.escapeshellarg('127.0.0.1 $(touch /tmp/lbr_security_test)'))
        ->toContain(escapeshellarg('database`touch /tmp/lbr_security_test`'));
});

it('shell-escapes connection credentials in the compressed mysql import command', function () {
    config()->set('database.connections.mysql-restore.host', '127.0.0.1 $(touch /tmp/lbr_security_test)');
    config()->set('database.connections.mysql-restore.port', '3306;touch /tmp/lbr_security_test');
    config()->set('database.connections.mysql-restore.username', 'root;touch /tmp/lbr_security_test');
    config()->set('database.connections.mysql-restore.password', "secret';touch /tmp/lbr_security_test;'");
    config()->set('database.connections.mysql-restore.database', 'database`touch /tmp/lbr_security_test`');

    $command = app(MySql::class)->getImportCommand('/tmp/backup.sql.gz', 'mysql-restore');

    expect($command)
        ->toContain('-u '.escapeshellarg('root;touch /tmp/lbr_security_test'))
        ->toContain('-p'.escapeshellarg("secret';touch /tmp/lbr_security_test;'"))
        ->toContain('-P '.escapeshellarg('3306;touch /tmp/lbr_security_test'))
        ->toContain('-h '.escapeshellarg('127.0.0.1 $(touch /tmp/lbr_security_test)'))
        ->toContain(escapeshellarg('database`touch /tmp/lbr_security_test`'));
});

it('shell-escapes the configured dump options in the mysql import command', function () {
    config()->set('database.connections.mysql-restore.dump.options', '--skip-ssl; touch /tmp/lbr_security_test');

    $command = app(MySql::class)->getImportCommand('/tmp/backup.sql', 'mysql-restore');

    expect($command)
        ->toContain(escapeshellarg('--skip-ssl;').' '.escapeshellarg('touch').' '.escapeshellarg('/tmp/lbr_security_test'));
});

it('keeps quoted dump options with spaces as a single argument', function () {
    config()->set('database.connections.mysql-restore.dump.options', '--skip-ssl --ssl-ca="/path with space/ca.pem"');

    $command = app(MySql::class)->getImportCommand('/tmp/backup.sql', 'mysql-restore');

    expect($command)
        ->toContain(escapeshellarg('--skip-ssl').' '.escapeshellarg('--ssl-ca=/path with space/ca.pem'));
});

it('shell-escapes the configured binary path in the mysql import command', function () {
    config()->set('database.connections.mysql-restore.dump.dump_binary_path', '/usr/bin/;touch /tmp/lbr_security_test');

    $command = app(MySql::class)->getImportCommand('/tmp/backup.sql', 'mysql-restore');

    expect($command)->toContain(escapeshellarg('/usr/bin/;touch /tmp/lbr_security_test'.DIRECTORY_SEPARATOR.'mysql'));
});
