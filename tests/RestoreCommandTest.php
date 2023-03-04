<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\Commands\RestoreCommand;

it('downloads remote backup, imports a single mysql dump into mysql database', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-01-28-mysql-no-compression-no-encryption.zip',
        '--database' => 'mysql',
    ])->assertSuccessful();

    $result = DB::connection('mysql')->table('users')->count();

    expect($result)->toBe(10);
});

it('downloads remote backup, decrypts and imports a single mysql dump into mysql database', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-01-28-mysql-no-compression-encrypted.zip',
        '--database' => 'mysql',
        '--password' => 'password',
    ])->assertSuccessful();

    $result = DB::connection('mysql')->table('users')->count();

    expect($result)->toBe(10);
});

it('downloads and decompresses remote backup, imports a single mysql dump into mysql database', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-01-28-mysql-compression-no-encryption.zip',
        '--database' => 'mysql',
    ])->assertSuccessful();

    $result = DB::connection('mysql')->table('users')->count();

    expect($result)->toBe(10);
});

it('downloads, decompresses remote backup, decrypts and imports a single mysql dump into mysql database', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-01-28-mysql-compression-encrypted.zip',
        '--database' => 'mysql',
        '--password' => 'password',
    ])->assertSuccessful();

    $result = DB::connection('mysql')->table('users')->count();

    expect($result)->toBe(10);
});

// sqlite
it('restores database backup into sqlite database', function (string $backup, ?string $password = null) {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => $backup,
        '--database' => 'sqlite',
        '--password' => $password,
    ])->assertSuccessful();

    $result = DB::connection('sqlite')->table('users')->count();

    expect($result)->toBe(10);
})->with([
    [
        'backup' => 'Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip',
        'password' => null,
    ],
    [
        'backup' => 'Laravel/2023-02-28-sqlite-compression-no-encryption.zip',
        'password' => null,
    ],
    [
        'backup' => 'Laravel/2023-02-28-sqlite-compression-encrypted.zip',
        'password' => 'password',
    ],
]);
