<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Process\Process;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

class MySql extends DbImporter
{
    private TemporaryDirectory $temporaryDirectory;

    /**
     * @throws ImportFailed
     */
    public function importToDatabase(string $dumpFile): void
    {
        $process = $this->getProcess($dumpFile);

        $process->run();

        $this->checkIfImportWasSuccessful($process, $dumpFile);
    }

    public function getImportCommand(string $pathToDump)
    {
        $temporaryDirectoryPath = config('backup.backup.temporary_directory') ?? storage_path('app/backup-temp');

        $this->temporaryDirectory = (new TemporaryDirectory($temporaryDirectoryPath))
            ->name('temp')
            ->force()
            ->create()
            ->empty();

        $dumper = DbDumperFactory::createFromConnection('mysql');
        $importToDatabase = $dumper->getDbName();
        // $importToDatabase = $pendingRestore->database;

        file_put_contents($this->temporaryDirectory->path('credentials.dat'), $dumper->getContentsOfCredentialsFile());

        $temporaryCredentialsFile = $this->temporaryDirectory->path('credentials.dat');

        // Build Shell Command to import a gzipped SQL file to a MySQL database
        if (str($pathToDump)->endsWith('gz')) {
            $command = $this->getMySqlImportCommandForCompressedDump($pathToDump, $temporaryCredentialsFile, $importToDatabase);
        } else {
            $command = $this->getMySqlImportCommandForUncompressedDump($temporaryCredentialsFile, $importToDatabase, $pathToDump);
        }

        return $command;
    }

    private function getMySqlImportCommandForCompressedDump(string $storagePathToDatabaseFile, mixed $temporaryCredentialsFile, string $importToDatabase): string
    {
        return collect([
            // zcat
            // "{$pathToZcatBinary} {$storagePathToDatabaseFile}",

            // gzip
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

    private function getProcess(string $dumpFile): Process
    {
        $command = $this->getImportCommand($dumpFile);

        return Process::fromShellCommandline($command, null, null, null, 0);
    }
}
