<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class MySql extends DbImporter
{
    private TemporaryDirectory $temporaryDirectory;

    public function getImportCommand(string $dumpFile, string $connection): string
    {
        $temporaryDirectoryPath = config('backup.backup.temporary_directory') ?? storage_path('app'.DIRECTORY_SEPARATOR.'backup-temp');

        $this->temporaryDirectory = (new TemporaryDirectory($temporaryDirectoryPath))
            ->name('temp')
            ->force()
            ->create()
            ->empty();

        if (config("database.connections.{$connection}.dump.dump_binary_path")) {
            $this->setDumpBinaryPath(config("database.connections.{$connection}.dump.dump_binary_path"));
        }

        $dumper = DbDumperFactory::createFromConnection($connection);
        $importToDatabase = $dumper->getDbName();

        file_put_contents($this->temporaryDirectory->path('credentials.dat'), $dumper->getContentsOfCredentialsFile());

        $temporaryCredentialsFile = $this->temporaryDirectory->path('credentials.dat');

        // Build Shell Command to import a gzipped SQL file to a MySQL database
        if (str($dumpFile)->endsWith('gz')) {
            $command = $this->getMySqlImportCommandForCompressedDump($dumpFile, $temporaryCredentialsFile, $importToDatabase);
        } else {
            $command = $this->getMySqlImportCommandForUncompressedDump($temporaryCredentialsFile, $importToDatabase, $dumpFile);
        }

        return $command;
    }

    public function getCliName(): string
    {
        return 'mysql';
    }

    private function getMySqlImportCommandForCompressedDump(string $storagePathToDatabaseFile, mixed $temporaryCredentialsFile, string $importToDatabase): string
    {
        $quote = $this->determineQuote();

        return collect([
            "gunzip < {$storagePathToDatabaseFile}",
            '|',
            "{$quote}{$this->dumpBinaryPath}mysql{$quote}",
            "--defaults-extra-file={$quote}{$temporaryCredentialsFile}{$quote}",
            $importToDatabase,
        ])->implode(' ');
    }

    private function getMySqlImportCommandForUncompressedDump(mixed $temporaryCredentialsFile, string $importToDatabase, string $storagePathToDatabaseFile): string
    {
        $quote = $this->determineQuote();

        return collect([
            "{$quote}{$this->dumpBinaryPath}mysql{$quote}",
            "--defaults-extra-file={$quote}{$temporaryCredentialsFile}{$quote}",
            $importToDatabase,
            '<',
            $storagePathToDatabaseFile,
        ])->implode(' ');
    }
}
