<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Wnx\LaravelBackupRestore\Actions\CheckDependenciesAction;
use Wnx\LaravelBackupRestore\Databases\DbImporter;
use Wnx\LaravelBackupRestore\DbImporterFactory;
use Wnx\LaravelBackupRestore\Exceptions\CliNotFound;

it('does not throw exception for supported database drivers', function ($connection) {
    app(CheckDependenciesAction::class)->execute($connection);
    $this->assertTrue(true);
})->with(['mysql', 'pgsql', 'sqlite']);

it('checks no binary for sqlite, which imports through PDO', function () {
    config()->set('database.connections.sqlite.dump.dump_binary_path', '/does/not/exist/');

    app(CheckDependenciesAction::class)->execute('sqlite');

    $this->assertTrue(true);
});

it('checks the binary at the configured dump_binary_path', function () {
    config()->set('database.connections.mysql.dump.dump_binary_path', '/does/not/exist/');

    app(CheckDependenciesAction::class)->execute('mysql');
})->throws(CliNotFound::class, '/does/not/exist/mysql');

it('throws exception if CLI dependency for given connection can not be found', function () {
    DbImporterFactory::extend('sqlsrv', new class extends DbImporter
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
    ->expectExceptionMessage('The "not-existing-cli" binary was not found.')
    ->expectException(CliNotFound::class);

it('looks up CLI dependencies with the lookup command of the current platform', function () {
    Process::fake();

    app(CheckDependenciesAction::class)->execute('mysql');

    $lookup = windows_os() ? 'where' : 'which';

    // Only the database client is looked up. Compressed dumps are decompressed
    // in PHP, so there is no gzip or bunzip2 to find.
    Process::assertRan(fn (PendingProcess $process) => $process->command === [$lookup, 'mysql']);
});
