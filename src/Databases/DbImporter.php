<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

abstract class DbImporter
{
    protected string $dumpBinaryPath = '';

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
        $process = Process::run($this->getImportCommand($dumpFile, $connection));

        $this->checkIfImportWasSuccessful($process, $dumpFile);
    }

    public function setDumpBinaryPath(string $dumpBinaryPath): self
    {
        if ($dumpBinaryPath !== '' && ! str_ends_with($dumpBinaryPath, DIRECTORY_SEPARATOR)) {
            $dumpBinaryPath .= DIRECTORY_SEPARATOR;
        }

        $this->dumpBinaryPath = $dumpBinaryPath;

        return $this;
    }

    protected function determineQuote(): string
    {
        return $this->isWindows() ? '"' : "'";
    }

    protected function isWindows(): bool
    {
        return str_starts_with(strtoupper(PHP_OS), 'WIN');
    }
}
