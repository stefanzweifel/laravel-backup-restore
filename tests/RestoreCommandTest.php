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
