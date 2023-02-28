<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Symfony\Component\Process\Process;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

class Sqlite
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
        // Shell command to import a gzipped SQL file to a sqlite database
        $command = 'sqlite3 '.config('database.connections.sqlite.database').' < '.$dumpFile;

        return $command;
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
