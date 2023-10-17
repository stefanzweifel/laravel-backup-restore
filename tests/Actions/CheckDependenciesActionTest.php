<?php

declare(strict_types=1);

use Wnx\LaravelBackupRestore\Actions\CheckDependenciesAction;
use Wnx\LaravelBackupRestore\Databases\DbImporter;
use Wnx\LaravelBackupRestore\DbImporterFactory;
use Wnx\LaravelBackupRestore\Exceptions\CliNotFound;

it('does not throw exception for supported database drivers', function ($connection) {
    app(CheckDependenciesAction::class)->execute($connection);
    $this->assertTrue(true);
})->with(['mysql', 'pgsql', 'sqlite']);

it('throws exception if CLI dependency for given connection can not be found', function () {
    DbImporterFactory::extend('sqlsrv', new class() extends DbImporter
    {
        public function getImportCommand(string $dumpFile, string $connection): string
        {
            return '';
        }

        public function getCliName(): string
        {
            return 'not-existing-cli';
        }
    });

    app(CheckDependenciesAction::class)->execute('unsupported-driver');

})
    ->expectExceptionMessage('CLI not-existing-cli not found. Please ensure not-existing-cli is in the PATH and available to your PHP process.')
    ->expectException(CliNotFound::class);
