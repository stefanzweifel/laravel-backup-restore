<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

abstract class DbImporter
{
    protected function checkIfImportWasSuccessful($process, string $dumpFile): void
    {
        if (! $process->isSuccessful()) {
            throw ImportFailed::processDidNotEndSuccessfully($process);
        }
    }
}
