<?php

namespace Wnx\LaravelBackupRestore\Databases;

use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Wnx\LaravelBackupRestore\PendingRestore;

class MySql
{
    public function getImportCommand(PendingRestore $pendingRestore, string $pathToDump): string
    {
        $dumper = DbDumperFactory::createFromConnection('mysql');
        $importToDatabase = $dumper->getDbName();
        // $importToDatabase = $pendingRestore->database;

        $tempFileHandle = tmpfile();
        fwrite($tempFileHandle, $dumper->getContentsOfCredentialsFile());
        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];
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
}
