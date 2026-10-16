<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Wnx\LaravelBackupRestore\Databases\MariaDb;
use Wnx\LaravelBackupRestore\Databases\MySql;
use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

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

it('uses the default binary path', function () {
    $command = app(MySql::class)->getImportCommand('irrelevant.sql', 'mysql-restore');

    expect($command)->toStartWith(escapeshellarg('mysql'));
});

it('uses the configured binary path', function () {
    $command = app(MySql::class)->getImportCommand('irrelevant.sql', 'mysql-restore-binary-path');

    expect($command)->toStartWith(escapeshellarg('/usr/bin/mysql'));
});

it('translates the configured dump options string into separate arguments', function () {
    config()->set('database.connections.mysql-restore.dump.options', '--skip-ssl --ssl-ca="/path with space/ca.pem"');

    $command = app(MySql::class)->getImportCommand('irrelevant.sql', 'mysql-restore');

    expect($command)
        ->toContain(escapeshellarg('--skip-ssl'))
        ->toContain(escapeshellarg('--ssl-ca=/path with space/ca.pem'));
});

it('throws import failed exception if mysql dump could not be imported', function () {
    app(MySql::class)->importToDatabase('file-does-not-exist', 'mysql');
})->throws(ImportFailed::class);

it('resolves the mariadb driver', function () {
    expect(app(MariaDb::class)->getCliName())->toBe('mariadb');
});
