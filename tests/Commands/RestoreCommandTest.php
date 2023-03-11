<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\Commands\RestoreCommand;
use Wnx\LaravelBackupRestore\Exceptions\NoBackupsFound;

// MySQL
it('restores mysql database', function (string $backup, ?string $password = null) {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => $backup,
        '--connection' => 'mysql',
        '--password' => $password,
    ])->assertSuccessful();

    $result = DB::connection('mysql')->table('users')->count();

    expect($result)->toBe(10);
})->with([
    [
        'backup' => 'Laravel/2023-01-28-mysql-no-compression-no-encryption.zip',
    ],
    [
        'backup' => 'Laravel/2023-01-28-mysql-compression-no-encryption.zip',
    ],
    [
        'backup' => 'Laravel/2023-01-28-mysql-no-compression-encrypted.zip',
        'password' => 'password',
    ],
    [
        'backup' => 'Laravel/2023-01-28-mysql-compression-encrypted.zip',
        'password' => 'password',
    ],
])->group('mysql');

// sqlite
it('restores sqlite database', function (string $backup, ?string $password = null) {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => $backup,
        '--connection' => 'sqlite',
        '--password' => $password,
    ])->assertSuccessful();

    $result = DB::connection('sqlite')->table('users')->count();

    expect($result)->toBe(10);
})->with([
    [
        'backup' => 'Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip',
    ],
    [
        'backup' => 'Laravel/2023-02-28-sqlite-compression-no-encryption.zip',
    ],
    [
        'backup' => 'Laravel/2023-02-28-sqlite-no-compression-encrypted.zip',
        'password' => 'password',
    ],
    [
        'backup' => 'Laravel/2023-02-28-sqlite-compression-encrypted.zip',
        'password' => 'password',
    ],
])->group('pgsql');

// pgsql
it('restores pgsql database', function (string $backup, ?string $password = null) {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => $backup,
        '--connection' => 'pgsql',
        '--password' => $password,
    ])->assertSuccessful();

    $result = DB::connection('pgsql')->table('users')->count();

    expect($result)->toBe(10);
})->with([
    [
        'backup' => 'Laravel/2023-03-04-pgsql-no-compression-no-encryption.zip',
    ],
    [
        'backup' => 'Laravel/2023-03-04-pgsql-compression-no-encryption.zip',
    ],
    [
        'backup' => 'Laravel/2023-03-04-pgsql-no-compression-encrypted.zip',
        'password' => 'password',
    ],
    [
        'backup' => 'Laravel/2023-03-04-pgsql-compression-encrypted.zip',
        'password' => 'password',
    ],
])->group('pgsql');

it('throws NoBackupsFound exception if no backups are found on given disk', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'local',
    ]);
})
    ->throws(NoBackupsFound::class)
    ->expectExceptionMessage('No backups found on disk local.');
