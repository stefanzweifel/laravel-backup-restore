<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Commands\RestoreCommand;
use Wnx\LaravelBackupRestore\Events\DatabaseReset;
use Wnx\LaravelBackupRestore\Events\LocalBackupRemoved;

use function Pest\Laravel\artisan;

// MySQL
it('restores mysql database', function (string $backup, ?string $password = null) {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => $backup,
        '--connection' => 'mysql-restore',
        '--password' => $password,
        '--no-interaction' => true,
    ])
        ->expectsQuestion("Proceed to restore \"{$backup}\" using the \"mysql-restore\" database connection. (Database: laravel_backup_restore, Host: 127.0.0.1, username: root)", true)
        ->expectsOutputToContain('All health checks passed.')
        ->assertSuccessful();

    $result = DB::connection('mysql-restore')->table('users')->count();

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
        '--connection' => 'sqlite-restore',
        '--password' => $password,
        '--no-interaction' => true,
    ])
        ->expectsQuestion("Proceed to restore \"{$backup}\" using the \"sqlite-restore\" database connection. (Database: database/database.sqlite)", true)
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
    $connection = config('database.connections.pgsql-restore');

    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => $backup,
        '--connection' => 'pgsql-restore',
        '--password' => $password,
        '--no-interaction' => true,
    ])
        ->expectsQuestion("Proceed to restore \"{$backup}\" using the \"pgsql-restore\" database connection. (Database: laravel_backup_restore, Host: {$connection['host']}, username: {$connection['username']})", true)
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

it('renders a failure instead of a stack trace if no backups are found on given disk', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'local',
    ])
        ->expectsOutputToContain('Restore failed.')
        ->expectsOutputToContain('No backups found on disk "local".')
        ->expectsOutputToContain('Looked for *.zip files')
        ->doesntExpectOutputToContain('vendor frames')
        ->assertExitCode(1);
});

it('renders a failure if the backup contains no database dumps', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-03-11-no-dumps.zip',
        '--connection' => 'sqlite-restore',
        '--no-interaction' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-03-11-no-dumps.zip" using the "sqlite-restore" database connection. (Database: database/database.sqlite)', true)
        ->expectsOutputToContain('Restore failed.')
        ->expectsOutputToContain('contains no database dumps.')
        ->expectsOutputToContain('not-a-sql-file.txt')
        ->doesntExpectOutputToContain('vendor frames')
        ->doesntExpectOutputToContain('NoDatabaseDumpsFound')
        ->assertExitCode(1);
})->group('sqlite');

it('shows the exception class when running with -v', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-03-11-no-dumps.zip',
        '--connection' => 'sqlite-restore',
        '--no-interaction' => true,
        '-v' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-03-11-no-dumps.zip" using the "sqlite-restore" database connection. (Database: database/database.sqlite)', true)
        ->expectsOutputToContain('Restore failed.')
        ->expectsOutputToContain('NoDatabaseDumpsFound')
        ->assertExitCode(1);
})->group('sqlite');

it('exits with 2 if the user does not confirm the restore', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip',
        '--connection' => 'sqlite-restore',
        '--no-interaction' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip" using the "sqlite-restore" database connection. (Database: database/database.sqlite)', false)
        ->expectsOutputToContain('Abort.')
        ->assertExitCode(2);
})->group('sqlite');

it('removes the downloaded and decompressed backup when the restore fails', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-03-11-no-dumps.zip',
        '--connection' => 'sqlite-restore',
        '--no-interaction' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-03-11-no-dumps.zip" using the "sqlite-restore" database connection. (Database: database/database.sqlite)', true)
        ->assertExitCode(1);

    expect(Storage::disk('local')->allFiles('backup-restore-temp'))->toBeEmpty();
})->group('sqlite');

it('keeps the downloaded and decompressed backup when the restore fails and --keep is passed', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-03-11-no-dumps.zip',
        '--connection' => 'sqlite-restore',
        '--no-interaction' => true,
        '--keep' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-03-11-no-dumps.zip" using the "sqlite-restore" database connection. (Database: database/database.sqlite)', true)
        ->assertExitCode(1);

    expect(Storage::disk('local')->allFiles('backup-restore-temp'))->not->toBeEmpty();
})->group('sqlite');

it('does not drop tables if the dump is empty', function () {
    DB::connection('sqlite')->statement('CREATE TABLE keep_me (id integer)');

    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-01-28-mysql-no-compression-no-encryption-empty-dump.zip',
        '--connection' => 'sqlite-restore',
        '--no-interaction' => true,
        '--reset' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-01-28-mysql-no-compression-no-encryption-empty-dump.zip" using the "sqlite-restore" database connection. (Database: database/database.sqlite) This drops all tables in that database and cannot be undone.', true)
        ->expectsOutputToContain('Restore failed.')
        ->expectsOutputToContain('is empty')
        ->assertExitCode(1);

    expect(Schema::connection('sqlite')->hasTable('keep_me'))->toBeTrue();
})->group('sqlite');

it('fails with a clear message if a configured health check is not a HealthCheck', function () {
    config(['backup-restore.health-checks' => [stdClass::class]]);

    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip',
        '--connection' => 'sqlite-restore',
        '--no-interaction' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip" using the "sqlite-restore" database connection. (Database: database/database.sqlite)', true)
        ->expectsOutputToContain('Restore failed.')
        ->expectsOutputToContain('stdClass')
        ->assertExitCode(1);
})->group('sqlite');

it('asks for password if password is not passed to command as an option', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-01-28-mysql-no-compression-encrypted.zip',
        '--connection' => 'mysql-restore',
    ])
        ->expectsConfirmation('Use encryption password from config?', false)
        ->expectsQuestion('What is the password to decrypt the backup? (leave empty if not encrypted)', 'password')
        ->expectsQuestion('Proceed to restore "Laravel/2023-01-28-mysql-no-compression-encrypted.zip" using the "mysql-restore" database connection. (Database: laravel_backup_restore, Host: 127.0.0.1, username: root)', true)
        ->assertSuccessful();

    $result = DB::connection('mysql-restore')->table('users')->count();

    expect($result)->toBe(10);
})->group('mysql');

it('reset database if option is provided', function () {
    Event::fake([DatabaseReset::class]);

    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-01-28-mysql-no-compression-no-encryption.zip',
        '--connection' => 'mysql-restore',
        '--password' => null,
        '--no-interaction' => true,
        '--reset' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-01-28-mysql-no-compression-no-encryption.zip" using the "mysql-restore" database connection. (Database: laravel_backup_restore, Host: 127.0.0.1, username: root) This drops all tables in that database and cannot be undone.', true)
        ->assertSuccessful();

    Event::assertDispatched(DatabaseReset::class);
})->group('mysql');

it('restores database from backup that contains multiple mysql dumps', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-01-28-mysql-no-compression-no-encryption-multiple-dumps.zip',
        '--connection' => 'mysql-restore',
        '--password' => null,
        '--no-interaction' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-01-28-mysql-no-compression-no-encryption-multiple-dumps.zip" using the "mysql-restore" database connection. (Database: laravel_backup_restore, Host: 127.0.0.1, username: root)', true)
        ->assertSuccessful();

    $result = DB::connection('mysql')->table('users')->count();

    expect($result)->toBe(10);
});

it('shows error message if health check after import fails', function () {
    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-01-28-mysql-no-compression-no-encryption-empty-dump.zip',
        '--connection' => 'mysql-restore',
        '--password' => null,
        '--no-interaction' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-01-28-mysql-no-compression-no-encryption-empty-dump.zip" using the "mysql-restore" database connection. (Database: laravel_backup_restore, Host: 127.0.0.1, username: root)', true)
        ->expectsOutputToContain('Database has not tables after restore.')
        ->assertFailed();
});

it('restores backup when --backup path is in a different directory than config backup name', function () {
    // Simulate a multi-tenant scenario where the backup lives under a different name
    // than config('backup.backup.name'). Without the fix this throws NoBackupsFound
    // because the code would list files under the config name directory first.
    config(['backup.backup.name' => 'NonExistentApp']);

    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip',
        '--connection' => 'sqlite-restore',
        '--no-interaction' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip" using the "sqlite-restore" database connection. (Database: database/database.sqlite)', true)
        ->assertSuccessful();

    $result = DB::connection('sqlite')->table('users')->count();

    expect($result)->toBe(10);
})->group('sqlite');

it('does not clear downloaded backup if --keep option is being used', function () {
    Event::fake([LocalBackupRemoved::class]);

    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => 'Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip',
        '--connection' => 'sqlite-restore',
        '--password' => null,
        '--no-interaction' => true,
        '--keep' => true,
    ])
        ->expectsQuestion('Proceed to restore "Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip" using the "sqlite-restore" database connection. (Database: database/database.sqlite)', true)
        ->assertSuccessful();

    Event::assertNotDispatched(LocalBackupRemoved::class);
    $files = Storage::disk('local')->allFiles('backup-restore-temp');

    expect($files)->not->toBeEmpty();

})->group('sqlite');

it('restores pgsql database with binary dump', function (string $backup, ?string $password = null) {
    config([
        'backup.backup.database_dump_file_extension' => 'backup',
    ]);
    artisan('db:wipe', [
        '--database' => 'pgsql-restore',
    ]);

    $connection = config('database.connections.pgsql-restore');

    $this->artisan(RestoreCommand::class, [
        '--disk' => 'remote',
        '--backup' => $backup,
        '--connection' => 'pgsql-restore',
        '--password' => $password,
        '--no-interaction' => true,
    ])
        ->expectsQuestion("Proceed to restore \"{$backup}\" using the \"pgsql-restore\" database connection. (Database: laravel_backup_restore, Host: {$connection['host']}, username: {$connection['username']})", true)
        ->assertSuccessful();

    $result = DB::connection('pgsql-restore')->table('users')->count();

    expect($result)->toBe(1);

    artisan('db:wipe', [
        '--database' => 'pgsql-restore',
    ]);
})->with([
    [
        'backup' => 'Laravel/2025-12-26-pgsql-no-compression-custom-extension-binary-dump.zip',
    ],
])->group('pgsql');
