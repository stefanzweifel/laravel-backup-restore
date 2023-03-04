<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\Commands\RestoreCommand;

// MySQL
it('restores mysql database', function (string $backup, ?string $password = null) {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => $backup,
        '--database' => 'mysql',
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
]);

// sqlite
it('restores sqlite database', function (string $backup, ?string $password = null) {
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
]);
