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
        return collect([
            "gunzip < {$storagePathToDatabaseFile}",
            '|',
            'mysql',
            "--defaults-extra-file=\"{$temporaryCredentialsFile}\"",
            $importToDatabase,
        ])->implode(' ');
    }

    private function getMySqlImportCommandForUncompressedDump(mixed $temporaryCredentialsFile, string $importToDatabase, string $storagePathToDatabaseFile): string
    {
        return collect([
            'mysql',
            "--defaults-extra-file=\"{$temporaryCredentialsFile}\"",
            $importToDatabase,
            '<',
            $storagePathToDatabaseFile,
        ])->implode(' ');
    }
}
