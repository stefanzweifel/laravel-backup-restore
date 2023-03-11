<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

abstract class DbImporter
{
    /**
     * @throws ImportFailed
     */
    protected function checkIfImportWasSuccessful($process, string $dumpFile): void
    {
        if (! $process->isSuccessful()) {
            throw ImportFailed::processDidNotEndSuccessfully($process);
        }

        event(new DatabaseDumpImportWasSuccessful($dumpFile));
    }

    abstract public function importToDatabase(string $dumpFile): void;
}
