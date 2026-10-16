<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter;

use Wnx\LaravelBackupRestore\DbImporter\Databases\MariaDb;
use Wnx\LaravelBackupRestore\DbImporter\Databases\MySql;
use Wnx\LaravelBackupRestore\DbImporter\Databases\PostgreSql;
use Wnx\LaravelBackupRestore\DbImporter\Databases\Sqlite;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotStartImport;

class DbImporterFactory
{
    /**
     * @throws CannotStartImport
     */
    public static function forDriver(string $driver): DbImporter
    {
        return match (strtolower($driver)) {
            'mysql' => new MySql,
            'mariadb' => new MariaDb,
            'pgsql', 'postgres', 'postgresql' => new PostgreSql,
            'sqlite', 'sqlite3' => new Sqlite,
            default => throw CannotStartImport::create("Cannot import a dump for the database driver `{$driver}`. Use `mysql`, `mariadb`, `pgsql` or `sqlite`."),
        };
    }
}
