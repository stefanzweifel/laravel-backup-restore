<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Exceptions\CannotCreateDbDumper;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Symfony\Component\Process\Process;
use Wnx\LaravelBackupRestore\PendingRestore;

class ImportMySqlDumpAction
{
    /**
     * @throws CannotCreateDbDumper
     */
    public function execute(PendingRestore $pendingRestore)
    {
        if ($this->hasNoDbDumpsDirectory($pendingRestore)) {
            throw new \Exception('No DB Dumps found in Backup');
        }

        $dbDumps = $this->getAvailableDbDumps($pendingRestore);

        $dumper = DbDumperFactory::createFromConnection('mysql');
        $importToDatabase = $dumper->getDbName();
        // $importToDatabase = $pendingRestore->database;

        $tempFileHandle = tmpfile();
        fwrite($tempFileHandle, $dumper->getContentsOfCredentialsFile());
        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];
        $pathToZcatBinary = config('backup-restore.gunzip');

        // TODO: Loop over all available dumps and import them

        // Create Absolute Path
        $storagePathToDatabaseFile = Storage::disk($pendingRestore->restoreDisk)->path($dbDumps[0]);

        // TODO: Make path to mysql binary configurable
        $pathToMySqlBinary = '/Users/Shared/DBngin/mysql/8.0.19/bin/mysql';

        // Build Shell Command to import a gzipped SQL file to a MySQL database
        if (str($storagePathToDatabaseFile)->endsWith('gz')) {
            $command = $this->getMySqlImportCommandForCompressedDump($pathToZcatBinary, $storagePathToDatabaseFile, $pathToMySqlBinary, $temporaryCredentialsFile, $importToDatabase);
        } else {
            $command = $this->getMySqlImportCommandForUncompressedDump($pathToMySqlBinary, $temporaryCredentialsFile, $importToDatabase, $storagePathToDatabaseFile);
        }

        $process = Process::fromShellCommandline($command, null, null, null, 0);
        $process->run();

        if ($process->isSuccessful()) {
            // Fire Event that a single database backup was restored
            return;
        }

        dd('Import Failed', $process->getErrorOutput());

        $this->error('Import failed.'.$process->getErrorOutput());
    }

    private function hasNoDbDumpsDirectory(PendingRestore $pendingRestore): bool
    {
        // TODO: Move to PendingRestore class
        $decompressFolder = "/backup-restore-temp/$pendingRestore->restoreId/";

        return ! Storage::disk($pendingRestore->restoreDisk)->has("$decompressFolder/db-dumps");
    }

    private function getAvailableDbDumps(PendingRestore $pendingRestore): array
    {
        // TODO: Move to PendingRestore class
        $decompressFolder = "/backup-restore-temp/$pendingRestore->restoreId/db-dumps";

        return Storage::disk($pendingRestore->restoreDisk)->files("$decompressFolder");
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
