<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter;

use Symfony\Component\Process\Process;
use Wnx\LaravelBackupRestore\DbImporter\Compressors\Compressor;
use Wnx\LaravelBackupRestore\DbImporter\Compressors\CompressorFactory;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotSetParameter;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotStartImport;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\DumpContainsMetaCommand;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\ImportFailed;

/**
 * Imports a database dump.
 *
 * Commands are built as an argument array and handed to Symfony's Process,
 * which runs the binary directly. Nothing passes through a shell, so values
 * taken from the configuration need no escaping.
 *
 * @phpstan-consistent-constructor
 */
abstract class DbImporter
{
    protected string $dbName = '';

    protected string $userName = '';

    protected string $password = '';

    protected string $host = '';

    protected ?int $port = null;

    protected string $socket = '';

    /** Seconds. 0 means no timeout. */
    protected int $timeout = 0;

    protected string $importBinaryPath = '';

    /** @var array<int, string> */
    protected array $extraOptions = [];

    protected ?Compressor $compressor = null;

    /** Files created for the duration of one import. */
    protected array $temporaryFiles = [];

    public static function create(): static
    {
        return new static;
    }

    /**
     * The argument vector that is executed, without the dump file. The dump is
     * streamed into the process on stdin.
     *
     * @return array<int, string>
     */
    abstract public function getImportCommand(): array;

    /**
     * The name of the binary, without any configured path prefix.
     */
    abstract public function getBinaryName(): string;

    /**
     * @throws CannotStartImport|DumpContainsMetaCommand|ImportFailed
     */
    public function importFromFile(string $dumpFile): void
    {
        if (! is_file($dumpFile) || ! is_readable($dumpFile)) {
            throw CannotStartImport::dumpFileNotReadable($dumpFile);
        }

        try {
            $this->prepareImport($dumpFile);
            $this->runImport($dumpFile);
        } finally {
            $this->cleanUpTemporaryFiles();
        }
    }

    /**
     * Runs before the command is built. Drivers use it to write credentials
     * files or to look at the dump format.
     */
    protected function prepareImport(string $dumpFile): void {}

    /**
     * @throws ImportFailed
     */
    protected function runImport(string $dumpFile): void
    {
        $input = $this->getProcessInput($dumpFile);

        $process = new Process(
            command: $this->getImportCommand(),
            env: $this->getEnvironmentVariables(),
            timeout: $this->timeout > 0 ? (float) $this->timeout : null,
        );

        $process->setInput($input);

        try {
            $process->run();
        } catch (\Throwable $throwable) {
            $process->stop(0);

            throw $throwable;
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
        }

        if (! $process->isSuccessful()) {
            throw ImportFailed::processDidNotEndSuccessfully($process);
        }
    }

    /**
     * What is written to the process' stdin: either the dump stream itself or
     * something that reads from it.
     *
     * @return resource|\Traversable<int, string>
     */
    protected function getProcessInput(string $dumpFile)
    {
        return $this->openDumpStream($dumpFile);
    }

    /**
     * A read stream over the dump contents, decompressed if needed.
     *
     * @return resource
     */
    public function openDumpStream(string $dumpFile)
    {
        return $this->determineCompressor($dumpFile)->open($dumpFile);
    }

    public function determineCompressor(string $dumpFile): Compressor
    {
        return $this->compressor ?? CompressorFactory::forDumpFile($dumpFile);
    }

    /**
     * Environment variables for the child process. Secrets go here rather than
     * into argv, where `ps` would show them to every local user.
     *
     * @return array<string, string>
     */
    public function getEnvironmentVariables(): array
    {
        return [];
    }

    public function getDbName(): string
    {
        return $this->dbName;
    }

    public function setDbName(string $dbName): static
    {
        $this->dbName = $dbName;

        return $this;
    }

    public function setUserName(string $userName): static
    {
        $this->userName = $userName;

        return $this;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function setHost(string $host): static
    {
        $this->host = $host;

        return $this;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function setSocket(string $socket): static
    {
        $this->socket = $socket;

        return $this;
    }

    public function setTimeout(int $timeout): static
    {
        if ($timeout < 0) {
            throw CannotSetParameter::mustNotBeNegative('timeout', $timeout);
        }

        $this->timeout = $timeout;

        return $this;
    }

    public function setImportBinaryPath(string $importBinaryPath): static
    {
        if ($importBinaryPath !== '' && ! str_ends_with($importBinaryPath, DIRECTORY_SEPARATOR)) {
            $importBinaryPath .= DIRECTORY_SEPARATOR;
        }

        $this->importBinaryPath = $importBinaryPath;

        return $this;
    }

    public function addExtraOption(string $extraOption): static
    {
        if (trim($extraOption) === '') {
            throw CannotSetParameter::mustNotBeEmpty('extraOption');
        }

        $this->extraOptions[] = $extraOption;

        return $this;
    }

    /**
     * @param  array<int, string>  $extraOptions
     */
    public function setExtraOptions(array $extraOptions): static
    {
        $this->extraOptions = [];

        foreach ($extraOptions as $extraOption) {
            $this->addExtraOption($extraOption);
        }

        return $this;
    }

    /**
     * Use this compressor instead of detecting one from the dump file.
     */
    public function useCompressor(Compressor $compressor): static
    {
        $this->compressor = $compressor;

        return $this;
    }

    protected function binary(): string
    {
        return $this->importBinaryPath.$this->getBinaryName();
    }

    /**
     * @throws CannotStartImport
     */
    protected function guardAgainstMissingDbName(): void
    {
        if ($this->dbName === '') {
            throw CannotStartImport::create('No database name was set. Call setDbName() before importing.');
        }
    }

    protected function createTemporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lbr-importer-');

        if ($path === false) {
            throw CannotStartImport::create('Could not create a temporary file for the import.');
        }

        // Narrow the permissions before anything is written to it.
        chmod($path, 0600);
        file_put_contents($path, $contents);

        $this->temporaryFiles[] = $path;

        return $path;
    }

    protected function cleanUpTemporaryFiles(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryFiles = [];
    }
}
