<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore;

use Illuminate\Support\Str;
use Wnx\LaravelBackupRestore\Databases\DbImporter;
use Wnx\LaravelBackupRestore\Databases\MySql;
use Wnx\LaravelBackupRestore\Databases\PostgreSql;
use Wnx\LaravelBackupRestore\Databases\Sqlite;
use Wnx\LaravelBackupRestore\Exceptions\CannotCreateDbImporter;

class DbImporterFactory
{
    protected static array $custom = [];

    /**
     * @throws CannotCreateDbImporter
     */
    public static function createFromConnection(string $dbConnectionName): DbImporter
    {
        if (config("database.connections.{$dbConnectionName}") === null) {
            throw CannotCreateDbImporter::unsupportedDriver($dbConnectionName);
        }

        return static::forDriver($dbConnectionName);
    }

    public static function forDriver(string $driver): DbImporter
    {
        $driver = Str::lower($driver);

        if (isset(static::$custom[$driver])) {
            return new static::$custom[$driver]();
        }

        return match ($driver) {
            'mysql' => new MySql(),
            'pgsql' => new PostgreSql(),
            'sqlite' => new Sqlite(),
            default => throw CannotCreateDbImporter::unsupportedDriver($driver),
        };
    }
}
