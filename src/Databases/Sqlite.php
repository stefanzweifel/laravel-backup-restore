<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Symfony\Component\Process\Process;
use Wnx\LaravelBackupRestore\DbImporter;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

class Sqlite extends DbImporter
{
    /**
     * @throws ImportFailed
     */
    public function importToDatabase(string $dumpFile): void
    {
        $process = $this->getProcess($dumpFile);

        $process->run();

        $this->checkIfImportWasSuccessful($process, $dumpFile);
    }

    public function getImportCommand(string $dumpFile): string
    {
        if (str($dumpFile)->endsWith('gz')) {
            // Shell command to import a gzipped SQL file to a sqlite database
            return 'gunzip -c '.$dumpFile.' | sqlite3 '.config('database.connections.sqlite.database');
        }

        return 'sqlite3 '.config('database.connections.sqlite.database').' < '.$dumpFile;
    }

    private function getProcess(string $dumpFile): Process
    {
        $command = $this->getImportCommand($dumpFile);

        return Process::fromShellCommandline($command, null, null, null, 0);
    }
}
