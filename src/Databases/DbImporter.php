<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Symfony\Component\Process\Process;
use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

abstract class DbImporter
{
    abstract public function getImportCommand(string $dumpFile): string;

    /**
     * @throws ImportFailed
     */
    protected function checkIfImportWasSuccessful(Process $process, string $dumpFile): void
    {
        if (! $process->isSuccessful()) {
            throw ImportFailed::processDidNotEndSuccessfully($process);
        }

        event(new DatabaseDumpImportWasSuccessful($dumpFile));
    }

    /**
     * @throws ImportFailed
     */
    public function importToDatabase(string $dumpFile): void
    {
        $process = $this->getProcess($dumpFile);

        $process->run();

        $this->checkIfImportWasSuccessful($process, $dumpFile);
    }

    protected function getProcess(string $dumpFile): Process
    {
        $command = $this->getImportCommand($dumpFile);

        return Process::fromShellCommandline($command, null, null, null, 0);
    }
}
