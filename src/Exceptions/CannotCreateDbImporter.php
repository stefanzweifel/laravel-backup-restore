<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;

class CannotCreateDbImporter extends Exception
{
    public static function unsupportedDriver(string $driver): self
    {
        return new static("Cannot create a importer for database driver `$driver`. Use `mysql`, `pgsql` or `sqlite`.");
    }
}
