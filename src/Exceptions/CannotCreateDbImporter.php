<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

class CannotCreateDbImporter extends \Exception
{
    public static function unsupportedDriver(string $driver): self
    {
        return new static("Cannot create a importer for db driver `{$driver}`. Use `mysql`, `pgsql`, `mongodb` or `sqlite`.");
    }
}
