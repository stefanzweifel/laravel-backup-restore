<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Databases;

use Wnx\LaravelBackupRestore\DbImporter\DbImporter;

class PostgreSql extends DbImporter
{
    protected string $searchPath = '';

    /** Set from the dump's magic number before the command is built. */
    protected bool $customFormatDump = false;

    public function __construct()
    {
        $this->port = 5432;
    }

    public function getBinaryName(): string
    {
        return $this->customFormatDump ? 'pg_restore' : 'psql';
    }

    public function setSearchPath(string $searchPath): static
    {
        $this->searchPath = $searchPath;

        return $this;
    }

    /**
     * A custom-format dump starts with the archive magic number. The extension
     * is not consulted: spatie/laravel-backup lets it be configured.
     */
    public function dumpIsCustomFormat(string $dumpFile): bool
    {
        $stream = $this->openDumpStream($dumpFile);

        try {
            return fread($stream, 5) === 'PGDMP';
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public function getImportCommand(): array
    {
        $command = [$this->binary(), '--no-password'];

        if ($this->customFormatDump) {
            $command[] = '--no-owner';
        } else {
            // Without this psql exits 0 after a statement fails mid-dump and
            // the restore is reported as successful.
            $command[] = '--set';
            $command[] = 'ON_ERROR_STOP=1';
        }

        if ($this->host !== '') {
            $command[] = '--host='.$this->host;
        }

        if ($this->port !== null) {
            $command[] = '--port='.$this->port;
        }

        if ($this->userName !== '') {
            $command[] = '--username='.$this->userName;
        }

        $command[] = '--dbname='.$this->dbName;

        foreach ($this->extraOptions as $extraOption) {
            $command[] = $extraOption;
        }

        return $command;
    }

    /**
     * @return array<string, string>
     */
    public function getEnvironmentVariables(): array
    {
        $environment = [];

        if ($this->password !== '') {
            $environment['PGPASSWORD'] = $this->password;
        }

        if ($this->searchPath !== '') {
            // libpq splits PGOPTIONS on whitespace and honours backslash escapes.
            $environment['PGOPTIONS'] = '--search_path='.str_replace(' ', '\\ ', $this->searchPath);
        }

        return $environment;
    }

    protected function prepareImport(string $dumpFile): void
    {
        $this->guardAgainstMissingDbName();

        $this->customFormatDump = $this->dumpIsCustomFormat($dumpFile);
    }
}
