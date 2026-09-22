<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Wnx\LaravelBackupRestore\DbImporter\DbImporter as FrameworkAgnosticDbImporter;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotStartImport;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\DumpContainsMetaCommand;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\ImportFailed as ImporterFailed;
use Wnx\LaravelBackupRestore\DbImporterFactory;
use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\CannotCreateDbImporter;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

/**
 * @deprecated Use Wnx\LaravelBackupRestore\DbImporter\DbImporter. This class is
 *             kept because the README documents extending it. The importers
 *             this package ships forward to the new implementation; a class of
 *             your own that builds a command string still runs through a shell
 *             the way it always did.
 */
abstract class DbImporter
{
    protected string $dumpBinaryPath = '';

    /**
     * @deprecated Commands are no longer built as shell strings. The importers
     *             this package ships return the argument vector joined with
     *             escapeshellarg() for display; that string is not what runs,
     *             and it does not include the dump file, which is streamed in
     *             on stdin.
     */
    abstract public function getImportCommand(string $dumpFile, string $connection): string;

    abstract public function getCliName(): string;

    /**
     * @throws ImportFailed
     */
    public function importToDatabase(string $dumpFile, string $connection): void
    {
        if ($this->forwardsToDbImporter()) {
            try {
                $this->resolveImporter($connection)->importFromFile($dumpFile);
            } catch (ImporterFailed|CannotStartImport|DumpContainsMetaCommand $exception) {
                throw ImportFailed::fromImporter($exception, $dumpFile);
            }

            event(new DatabaseDumpImportWasSuccessful($dumpFile));

            return;
        }

        $driver = config("database.connections.{$connection}.driver");
        $password = config("database.connections.{$connection}.password");

        $process = Process::forever()->env([
            $driver === 'pgsql' ? 'PGPASSWORD' : '' => $password,
        ])->run($this->getImportCommand($dumpFile, $connection));

        $this->checkIfImportWasSuccessful($process, $dumpFile);
    }

    public function setDumpBinaryPath(string $dumpBinaryPath): self
    {
        // Accept a path that already ends in either separator. On Windows
        // DIRECTORY_SEPARATOR is a backslash, so a configured "/usr/bin/"
        // would otherwise become "/usr/bin/" plus a backslash plus "mysql".
        if ($dumpBinaryPath !== '' && ! str_ends_with($dumpBinaryPath, '/') && ! str_ends_with($dumpBinaryPath, DIRECTORY_SEPARATOR)) {
            $dumpBinaryPath .= DIRECTORY_SEPARATOR;
        }

        $this->dumpBinaryPath = $dumpBinaryPath;

        return $this;
    }

    /**
     * True for the importers this package ships, which hand the import to
     * Wnx\LaravelBackupRestore\DbImporter. False for anything else, which
     * keeps the behaviour it had before that abstraction existed.
     */
    protected function forwardsToDbImporter(): bool
    {
        return false;
    }

    /**
     * The importer the forwarding path runs. DbImporterFactory::wrap()
     * overrides this to return the instance it already holds, so both routes
     * share the try/catch above and neither lets an importer exception escape.
     *
     * @throws CannotCreateDbImporter
     */
    protected function resolveImporter(string $connection): FrameworkAgnosticDbImporter
    {
        return DbImporterFactory::importerForConnection($connection);
    }

    /**
     * @param  array<int, string>  $command
     */
    protected static function commandForDisplay(array $command): string
    {
        return implode(' ', array_map(escapeshellarg(...), $command));
    }

    /**
     * @throws ImportFailed
     */
    protected function checkIfImportWasSuccessful(ProcessResult $process, string $dumpFile): void
    {
        if (! $process->successful()) {
            throw ImportFailed::processDidNotEndSuccessfully($process, $dumpFile);
        }

        event(new DatabaseDumpImportWasSuccessful($dumpFile));
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
