<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\DbImporter\Databases\MySql;
use Wnx\LaravelBackupRestore\DbImporter\Databases\PostgreSql;
use Wnx\LaravelBackupRestore\DbImporter\Databases\Sqlite;
use Wnx\LaravelBackupRestore\DbImporterFactory;
use Wnx\LaravelBackupRestore\PendingRestore;
use Wnx\LaravelBackupRestore\Tests\TestCase;

use function Pest\Laravel\artisan;

function lbrFixture(string $name): string
{
    return __DIR__.'/storage/Laravel/'.$name;
}

const LBR_SQLITE_BACKUP = 'Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip';

/**
 * The confirmation question the command asks for LBR_SQLITE_BACKUP. The text
 * has to match exactly, so it is built in one place.
 */
function lbrConfirmation(bool $reset = false): string
{
    $label = 'Proceed to restore "'.LBR_SQLITE_BACKUP.'" using the "sqlite-restore" database connection. (Database: database/database.sqlite)';

    return $reset
        ? $label.' This drops all tables in that database and cannot be undone.'
        : $label;
}

function mysqlImporter(string $connection = 'mysql-restore'): MySql
{
    $config = config("database.connections.{$connection}");

    return MySql::create()
        ->setDbName($config['database'])
        ->setUserName($config['username'])
        ->setPassword((string) $config['password'])
        ->setHost($config['host'])
        ->setPort((int) $config['port']);
}

function pgsqlImporter(string $connection = 'pgsql'): PostgreSql
{
    $config = config("database.connections.{$connection}");

    return PostgreSql::create()
        ->setDbName($config['database'])
        ->setUserName($config['username'])
        ->setPassword((string) $config['password'])
        ->setHost($config['host'])
        ->setPort((int) $config['port']);
}

function sqliteImporter(string $connection = 'sqlite'): Sqlite
{
    return Sqlite::create()->setDbName(config("database.connections.{$connection}.database"));
}

uses(TestCase::class)
    ->beforeEach(function () {
        // extend() writes to a static registry that survives the application,
        // so a driver one test registers is still there for the next one.
        (new ReflectionClass(DbImporterFactory::class))->setStaticPropertyValue('custom', []);

        // Delete all files in the temp directory
        Storage::disk('local')->deleteDirectory('backup-restore-temp');

        // Wipe all databases before each test
        artisan('db:wipe', ['--database' => 'mysql']);
        artisan('db:wipe', ['--database' => 'sqlite']);
        artisan('db:wipe', ['--database' => 'pgsql']);
        artisan('db:wipe', ['--database' => 'pgsql-restore']);
    })
    ->afterEach(function () {
        // Wipe all databases after each test
        artisan('db:wipe', ['--database' => 'mysql']);
        artisan('db:wipe', ['--database' => 'sqlite']);
        artisan('db:wipe', ['--database' => 'pgsql']);
        artisan('db:wipe', ['--database' => 'pgsql-restore']);

        // Delete all files in the temp directory
        Storage::disk('local')->deleteDirectory('backup-restore-temp');
    })
    ->in(__DIR__);

/**
 * Build a ZIP in the test itself and put it where the restore expects the
 * downloaded archive, so no binary fixture has to be committed.
 */
function putCraftedArchive(PendingRestore $pendingRestore, Closure $build): void
{
    $tmpPath = tempnam(sys_get_temp_dir(), 'lbr-test-').'.zip';

    $zip = new ZipArchive;
    $zip->open($tmpPath, ZipArchive::CREATE);
    $build($zip);
    $zip->close();

    Storage::disk('local')->put(
        $pendingRestore->getPathToLocalCompressedBackup(),
        file_get_contents($tmpPath)
    );

    unlink($tmpPath);
}
