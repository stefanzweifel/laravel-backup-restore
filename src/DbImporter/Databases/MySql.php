<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Databases;

use Wnx\LaravelBackupRestore\DbImporter\DbImporter;

class MySql extends DbImporter
{
    protected ?string $credentialsFile = null;

    public function __construct()
    {
        $this->port = 3306;
    }

    public function getBinaryName(): string
    {
        return 'mysql';
    }

    /**
     * @return array<int, string>
     */
    public function getImportCommand(): array
    {
        $command = [$this->binary()];

        // mysql only reads --defaults-extra-file when it is the first argument.
        if ($this->credentialsFile !== null) {
            $command[] = '--defaults-extra-file='.$this->credentialsFile;
        }

        foreach ($this->extraOptions as $extraOption) {
            $command[] = $extraOption;
        }

        $command[] = $this->dbName;

        return $command;
    }

    /**
     * Credentials go into a 0600 file instead of argv, where `ps` would show
     * the password to every local user.
     */
    public function getContentsOfCredentialsFile(): string
    {
        $lines = ['[client]'];

        if ($this->userName !== '') {
            $lines[] = 'user = "'.$this->escapeForOptionFile($this->userName).'"';
        }

        if ($this->password !== '') {
            $lines[] = 'password = "'.$this->escapeForOptionFile($this->password).'"';
        }

        if ($this->socket !== '') {
            $lines[] = 'socket = "'.$this->escapeForOptionFile($this->socket).'"';
        } else {
            if ($this->host !== '') {
                $lines[] = 'host = "'.$this->escapeForOptionFile($this->host).'"';
            }

            if ($this->port !== null) {
                $lines[] = 'port = '.$this->port;
            }
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    protected function prepareImport(string $dumpFile): void
    {
        $this->guardAgainstMissingDbName();

        $this->credentialsFile = $this->createTemporaryFile($this->getContentsOfCredentialsFile());
    }

    protected function cleanUpTemporaryFiles(): void
    {
        parent::cleanUpTemporaryFiles();

        $this->credentialsFile = null;
    }

    /**
     * A MySQL option file reads \ as an escape inside a double-quoted value,
     * and a value cannot span lines.
     */
    protected function escapeForOptionFile(string $value): string
    {
        return str_replace(
            ['\\', '"', "\n", "\r"],
            ['\\\\', '\\"', '\\n', '\\r'],
            $value
        );
    }
}
