<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

abstract class DbImporter
{
    abstract public function getImportCommand(string $dumpFile, string $connection): string;

    abstract public function getCliName(): string;

    /**
     * @throws ImportFailed
     */
    protected function checkIfImportWasSuccessful(ProcessResult $process, string $dumpFile): void
    {
        if (! $process->successful()) {
            throw ImportFailed::processDidNotEndSuccessfully($process);
        }

        event(new DatabaseDumpImportWasSuccessful($dumpFile));
    }

    /**
     * @throws ImportFailed
     */
    public function importToDatabase(string $dumpFile, string $connection): void
    {
        $process = Process::run($this->getImportCommand($dumpFile. $connection));

        $this->checkIfImportWasSuccessful($process, $dumpFile);
    }
}
