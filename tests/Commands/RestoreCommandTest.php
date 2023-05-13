<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Wnx\LaravelBackupRestore\Commands\RestoreCommand;
use Wnx\LaravelBackupRestore\Events\DatabaseReset;
use Wnx\LaravelBackupRestore\Exceptions\NoBackupsFound;

// MySQL
it('restores mysql database', function (string $backup, ?string $password = null) {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => $backup,
        '--connection' => 'mysql',
        '--password' => $password,
        '--no-interaction' => true,
    ])
        ->expectsQuestion("Proceed to restore \"{$backup}\" using the \"mysql\" database connection.", true)
        ->assertSuccessful();

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
        '--no-interaction' => true,
    ])
        ->expectsQuestion("Proceed to restore \"{$backup}\" using the \"sqlite\" database connection.", true)
        ->assertSuccessful();

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
])->group('sqlite');

// pgsql
it('restores pgsql database', function (string $backup, ?string $password = null) {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => $backup,
        '--connection' => 'pgsql',
        '--password' => $password,
        '--no-interaction' => true,
    ])
        ->expectsQuestion("Proceed to restore \"{$backup}\" using the \"pgsql\" database connection.", true)
        ->assertSuccessful();

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

it('asks for password if password is not passed to command as an option', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-01-28-mysql-no-compression-encrypted.zip',
        '--connection' => 'mysql',
    ])
        ->expectsConfirmation('Use encryption password from config?', false)
        ->expectsQuestion('What is the password to decrypt the backup? (leave empty if not encrypted)', 'password')
        ->expectsQuestion('Proceed to restore "Laravel/2023-01-28-mysql-no-compression-encrypted.zip" using the "mysql" database connection.', true)
        ->assertSuccessful();

    $result = DB::connection('mysql')->table('users')->count();

    expect($result)->toBe(10);
})->group('mysql');

it('reset database if option is provided', function () {
    Event::fake([DatabaseReset::class]);

    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-01-28-mysql-no-compression-no-encryption.zip',
        '--connection' => 'mysql',
        '--password' => null,
        '--no-interaction' => true,
        '--reset' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-01-28-mysql-no-compression-no-encryption.zip" using the "mysql" database connection.', true)
        ->assertSuccessful();

    Event::assertDispatched(DatabaseReset::class);
})->group('mysql');

it('restores database from backup that contains multiple mysql dumps', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-01-28-mysql-no-compression-no-encryption-multiple-dumps.zip',
        '--connection' => 'mysql',
        '--password' => null,
        '--no-interaction' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-01-28-mysql-no-compression-no-encryption-multiple-dumps.zip" using the "mysql" database connection.', true)
        ->assertSuccessful();

    $result = DB::connection('mysql')->table('users')->count();

    expect($result)->toBe(10);
});
