<?php

declare(strict_types=1);

use Wnx\LaravelBackupRestore\DbImporterFactory;
use Wnx\LaravelBackupRestore\Exceptions\CannotCreateDbImporter;

it('returns db importer instances for given database driver', function ($driver, $expectedClass) {
    expect(DbImporterFactory::forDriver($driver))->toBeInstanceOf($expectedClass);
})->with([
    [
        'driver' => 'mysql',
        'expected' => \Wnx\LaravelBackupRestore\Databases\MySql::class,
    ],
    [
        'driver' => 'sqlite',
        'expected' => \Wnx\LaravelBackupRestore\Databases\Sqlite::class,
    ],
    [
        'driver' => 'pgsql',
        'expected' => \Wnx\LaravelBackupRestore\Databases\PostgreSql::class,
    ],
]);

it('throws exception if no db importer instance can be created for unsupported river', function () {
    DbImporterFactory::forDriver('unsupported');
})->throws(CannotCreateDbImporter::class);
