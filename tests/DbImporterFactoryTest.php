<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Wnx\LaravelBackupRestore\Databases\DbImporter;
use Wnx\LaravelBackupRestore\Databases\MariaDb;
use Wnx\LaravelBackupRestore\Databases\MySql;
use Wnx\LaravelBackupRestore\Databases\PostgreSql;
use Wnx\LaravelBackupRestore\Databases\Sqlite;
use Wnx\LaravelBackupRestore\DbImporter\Databases\MySql as MySqlImporter;
use Wnx\LaravelBackupRestore\DbImporter\Databases\PostgreSql as PostgreSqlImporter;
use Wnx\LaravelBackupRestore\DbImporter\Databases\Sqlite as SqliteImporter;
use Wnx\LaravelBackupRestore\DbImporterFactory;
use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\CannotCreateDbImporter;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

it('returns db importer instances for given database driver', function ($connectionName, $expected) {
    expect(DbImporterFactory::createFromConnection($connectionName))->toBeInstanceOf($expected);
})->with([
    ['connectionName' => 'mysql', 'expected' => MySql::class],
    ['connectionName' => 'mysql-restore', 'expected' => MySql::class],
    ['connectionName' => 'sqlite', 'expected' => Sqlite::class],
    ['connectionName' => 'sqlite-restore', 'expected' => Sqlite::class],
    ['connectionName' => 'pgsql', 'expected' => PostgreSql::class],
    ['connectionName' => 'pgsql-restore', 'expected' => PostgreSql::class],
    ['connectionName' => 'mariadb', 'expected' => MariaDb::class],
]);

it('configures the importer from the connection', function () {
    $importer = DbImporterFactory::importerForConnection('mysql-restore-binary-path');

    expect($importer)->toBeInstanceOf(MySqlImporter::class);

    $command = $importer->getImportCommand();
    expect($command[0])->toBe('/usr/bin/mysql');
    expect(end($command))->toBe(config('database.connections.mysql-restore-binary-path.database'));

    $credentials = $importer->getContentsOfCredentialsFile();
    expect($credentials)->toContain('user = "'.config('database.connections.mysql-restore-binary-path.username').'"');
});

it('copies the search path onto the pgsql importer', function () {
    $importer = DbImporterFactory::importerForConnection('pgsql');

    expect($importer)->toBeInstanceOf(PostgreSqlImporter::class);
    expect($importer->getEnvironmentVariables())->toHaveKey('PGOPTIONS', '--search_path=public');
});

it('gives the sqlite importer the database file as its name', function () {
    $importer = DbImporterFactory::importerForConnection('sqlite');

    expect($importer)->toBeInstanceOf(SqliteImporter::class);
    expect($importer->getDbName())->toBe(config('database.connections.sqlite.database'));
});

it('resolves a connection configured with a url', function () {
    config()->set('database.connections.url-connection', [
        'driver' => 'pgsql',
        'url' => 'postgres://someone:hunter2@db.example.com:5433/app_database',
    ]);

    $importer = DbImporterFactory::importerForConnection('url-connection');

    expect($importer->getImportCommand())->toContain(
        '--host=db.example.com',
        '--port=5433',
        '--username=someone',
        '--dbname=app_database',
    );
    expect($importer->getEnvironmentVariables())->toHaveKey('PGPASSWORD', 'hunter2');
});

it('splits the dump options string into arguments', function (string $options, array $expected) {
    expect(DbImporterFactory::parseOptions($options))->toBe($expected);
})->with([
    ['', []],
    ['--skip-ssl', ['--skip-ssl']],
    ['--skip-ssl --force', ['--skip-ssl', '--force']],
    ['--ssl-ca="/path with space/ca.pem"', ['--ssl-ca=/path with space/ca.pem']],
    ["--ssl-ca='/path with space/ca.pem'", ['--ssl-ca=/path with space/ca.pem']],
    ['--skip-ssl; touch /tmp/pwned', ['--skip-ssl;', 'touch', '/tmp/pwned']],
]);

it('returns a custom db importer registered as an instance', function () {
    DbImporterFactory::extend('sqlsrv', new class extends DbImporter
    {
        public function getImportCommand(string $dumpFile, string $connection): string
        {
            return 'import-command';
        }

        public function getCliName(): string
        {
            return 'sqlsrv';
        }
    });

    $instance = DbImporterFactory::createFromConnection('unsupported-driver');

    expect($instance->getImportCommand('path/to/dump/file', 'connection'))->toEqual('import-command');
});

it('returns a custom db importer registered as a closure', function () {
    DbImporterFactory::extend('sqlsrv', fn (array $config) => new class($config) extends DbImporter
    {
        public function __construct(private array $config) {}

        public function getImportCommand(string $dumpFile, string $connection): string
        {
            return 'import-'.$this->config['driver'];
        }

        public function getCliName(): string
        {
            return 'sqlsrv';
        }
    });

    $instance = DbImporterFactory::createFromConnection('unsupported-driver');

    expect($instance->getImportCommand('path/to/dump/file', 'connection'))->toEqual('import-sqlsrv');
});

it('returns a custom db importer registered as a class name', function () {
    DbImporterFactory::extend('sqlsrv', CustomSqlServerImporter::class);

    expect(DbImporterFactory::createFromConnection('unsupported-driver'))
        ->toBeInstanceOf(CustomSqlServerImporter::class);
});

it('throws exception if no db importer instance can be created for connection', function () {
    DbImporterFactory::createFromConnection('unsupported');
})->throws(CannotCreateDbImporter::class);

it('throws exception if no db importer instance can be created for driver', function () {
    DbImporterFactory::createFromConnection('unsupported-driver');
})->throws(CannotCreateDbImporter::class);

class CustomSqlServerImporter extends DbImporter
{
    public function getImportCommand(string $dumpFile, string $connection): string
    {
        return 'import-command';
    }

    public function getCliName(): string
    {
        return 'sqlsrv';
    }
}

it('reports a failure from a custom framework-agnostic importer as a BackupRestoreException', function () {
    // Everything the command catches implements BackupRestoreException. An
    // importer registered through extend() has to be held to that too, or the
    // user gets a stack trace instead of a message.
    DbImporterFactory::extend(
        'sqlite',
        fn (array $config) => DbImporterFactory::importerForConnection('sqlite')
    );

    $importer = DbImporterFactory::createFromConnection('sqlite');

    expect(fn () => $importer->importToDatabase('/does/not/exist.sql', 'sqlite'))
        ->toThrow(ImportFailed::class);
});

it('dispatches the success event for a custom framework-agnostic importer', function () {
    Event::fake();

    DbImporterFactory::extend(
        'sqlite',
        fn (array $config) => DbImporterFactory::importerForConnection('sqlite')
    );

    $dumpFile = __DIR__.'/storage/Laravel/2023-02-28-sqlite-no-compression-no-encryption.sql';

    DbImporterFactory::createFromConnection('sqlite')->importToDatabase($dumpFile, 'sqlite');

    Event::assertDispatched(
        fn (DatabaseDumpImportWasSuccessful $event) => $event->absolutePathToDump === $dumpFile
    );
});
