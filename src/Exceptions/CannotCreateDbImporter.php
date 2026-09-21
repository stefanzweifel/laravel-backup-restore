<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;

class CannotCreateDbImporter extends Exception implements BackupRestoreException
{
    protected function __construct(
        public readonly ?string $connectionName,
        public readonly ?string $driver,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function configNotFound(string $connectionName): self
    {
        return new static(
            connectionName: $connectionName,
            driver: null,
            message: "Database connection \"{$connectionName}\" is not configured.",
        );
    }

    public static function unsupportedDriver(string $driver): self
    {
        return new static(
            connectionName: null,
            driver: $driver,
            message: "Database driver \"{$driver}\" is not supported.",
        );
    }

    public function hint(): ?string
    {
        if ($this->driver === null) {
            return 'Add the connection to config/database.php, or pass an existing one with --connection.';
        }

        return 'Supported drivers: mysql, pgsql, sqlite. Register your own with DbImporterFactory::extend().';
    }
}
