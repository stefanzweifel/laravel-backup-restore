<?php

declare(strict_types=1);

use Wnx\LaravelBackupRestore\Databases\DbImporter;
use Wnx\LaravelBackupRestore\DbImporterFactory;
use Wnx\LaravelBackupRestore\Exceptions\CannotCreateDbImporter;

it('returns db importer instances for given database driver', function ($connectionName, $expectedClass) {
    expect(DbImporterFactory::createFromConnection($connectionName))->toBeInstanceOf($expectedClass);
})->with([
    [
        'connectionName' => 'mysql',
        'expected' => \Wnx\LaravelBackupRestore\Databases\MySql::class,
    ],
    [
        'connectionName' => 'mysql-restore',
        'expected' => \Wnx\LaravelBackupRestore\Databases\MySql::class,
    ],
    [
        'connectionName' => 'sqlite',
        'expected' => \Wnx\LaravelBackupRestore\Databases\Sqlite::class,
    ],
    [
        'connectionName' => 'sqlite-restore',
        'expected' => \Wnx\LaravelBackupRestore\Databases\Sqlite::class,
    ],
    [
        'connectionName' => 'pgsql',
        'expected' => \Wnx\LaravelBackupRestore\Databases\PostgreSql::class,
    ],
    [
        'connectionName' => 'pgsql-restore',
        'expected' => \Wnx\LaravelBackupRestore\Databases\PostgreSql::class,
    ],
]);

it('returns custom db importer instance for the given database driver', function () {
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

it('throws exception if no db importer instance can be created for connection')
    ->tap(fn () => DbImporterFactory::createFromConnection('unsupported'))
    ->throws(CannotCreateDbImporter::class);

it('throws exception if no db importer instance can be created for driver')
    ->tap(fn () => DbImporterFactory::createFromConnection('unsupported-driver'))
    ->throws(CannotCreateDbImporter::class);
