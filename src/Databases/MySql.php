<?php

namespace Wnx\LaravelBackupRestore\Databases;

use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Process\Process;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;
use Wnx\LaravelBackupRestore\PendingRestore;

class MySql
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

        file_put_contents($this->temporaryDirectory->path('credentials.txt'), $dumper->getContentsOfCredentialsFile());

        $temporaryCredentialsFile = $this->temporaryDirectory->path('credentials.txt');

        $pathToZcatBinary = config('backup-restore.gunzip');

        // TODO: Make path to mysql binary configurable
        $pathToMySqlBinary = '/Users/Shared/DBngin/mysql/8.0.19/bin/mysql';

        // Build Shell Command to import a gzipped SQL file to a MySQL database
        if (str($pathToDump)->endsWith('gz')) {
            $command = $this->getMySqlImportCommandForCompressedDump($pathToZcatBinary, $pathToDump, $pathToMySqlBinary, $temporaryCredentialsFile, $importToDatabase);
        } else {
            $command = $this->getMySqlImportCommandForUncompressedDump($pathToMySqlBinary, $temporaryCredentialsFile, $importToDatabase, $pathToDump);
        }

        return $command;
    }

    private function getMySqlImportCommandForCompressedDump(string $pathToZcatBinary, string $storagePathToDatabaseFile, string $pathToMySqlBinary, mixed $temporaryCredentialsFile, string $importToDatabase): string
    {
        return collect([
            // zcat
            // "{$pathToZcatBinary} {$storagePathToDatabaseFile}",

            // gzip
            "{$pathToZcatBinary} < {$storagePathToDatabaseFile}",
            '|',
            "'{$pathToMySqlBinary}'",
            "--defaults-extra-file=\"{$temporaryCredentialsFile}\"",
            $importToDatabase,
        ])->implode(' ');
    }

    private function getMySqlImportCommandForUncompressedDump(string $pathToMySqlBinary, mixed $temporaryCredentialsFile, string $importToDatabase, string $storagePathToDatabaseFile): string
    {
        return collect([
            "'{$pathToMySqlBinary}'",
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

    private function checkIfImportWasSuccessful($process, string $dumpFile): void
    {
        if (! $process->isSuccessful()) {
            throw ImportFailed::processDidNotEndSuccessfully($process);
        }
    }
}
