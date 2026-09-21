<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Str;
use Wnx\LaravelBackupRestore\Databases\DbImporter;
use Wnx\LaravelBackupRestore\Databases\MariaDb;
use Wnx\LaravelBackupRestore\Databases\MySql;
use Wnx\LaravelBackupRestore\Databases\PostgreSql;
use Wnx\LaravelBackupRestore\Databases\Sqlite;
use Wnx\LaravelBackupRestore\DbImporter\DbImporter as FrameworkAgnosticDbImporter;
use Wnx\LaravelBackupRestore\DbImporter\DbImporterFactory as ImporterForDriver;
use Wnx\LaravelBackupRestore\DbImporter\Databases\PostgreSql as PostgreSqlImporter;
use Wnx\LaravelBackupRestore\DbImporter\Databases\Sqlite as SqliteImporter;
use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\CannotCreateDbImporter;

/**
 * Translates a Laravel database connection into a configured importer.
 */
class DbImporterFactory
{
    /** @var array<string, Closure|class-string|DbImporter> */
    protected static array $custom = [];

    /**
     * @throws CannotCreateDbImporter
     */
    public static function createFromConnection(string $dbConnectionName): DbImporter
    {
        return static::forDriver(
            (string) (static::configFor($dbConnectionName)['driver'] ?? ''),
            static::configFor($dbConnectionName),
        );
    }

    /**
     * The framework-agnostic importer for a connection, with every value from
     * config/database.php copied onto it.
     *
     * @throws CannotCreateDbImporter
     */
    public static function importerForConnection(string $dbConnectionName): FrameworkAgnosticDbImporter
    {
        $config = static::configFor($dbConnectionName);
        $driver = Str::lower((string) ($config['driver'] ?? ''));

        try {
            $importer = ImporterForDriver::forDriver($driver);
        } catch (\Throwable) {
            throw CannotCreateDbImporter::unsupportedDriver($driver);
        }

        return static::configure($importer, $config);
    }

    /**
     * Register an importer for a driver the package does not ship.
     *
     * Pass a closure receiving the connection config, or a class name. Passing
     * an instance still works but is deprecated: it was never usable, because
     * the factory did `new $instance`.
     *
     * @param  Closure|class-string|DbImporter  $callback
     */
    public static function extend(string $driver, Closure|string|DbImporter $callback): void
    {
        static::$custom[Str::lower($driver)] = $callback;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws CannotCreateDbImporter
     */
    protected static function configFor(string $dbConnectionName): array
    {
        $config = config("database.connections.$dbConnectionName");

        if ($config === null) {
            throw CannotCreateDbImporter::configNotFound($dbConnectionName);
        }

        // Resolves a DATABASE_URL-style `url` key the way Laravel itself does.
        return (new ConfigurationUrlParser)->parseConfiguration($config);
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws CannotCreateDbImporter
     */
    protected static function forDriver(string $driver, array $config): DbImporter
    {
        $driver = Str::lower($driver);

        if (isset(static::$custom[$driver])) {
            return static::resolveCustom(static::$custom[$driver], $config);
        }

        return match ($driver) {
            'mysql' => new MySql,
            'mariadb' => new MariaDb,
            'pgsql' => new PostgreSql,
            'sqlite' => new Sqlite,
            default => throw CannotCreateDbImporter::unsupportedDriver($driver),
        };
    }

    /**
     * @param  Closure|class-string|DbImporter  $callback
     * @param  array<string, mixed>  $config
     *
     * @throws CannotCreateDbImporter
     */
    protected static function resolveCustom(Closure|string|DbImporter $callback, array $config): DbImporter
    {
        $resolved = match (true) {
            $callback instanceof Closure => $callback($config),
            is_string($callback) => new $callback,
            default => $callback,
        };

        if ($resolved instanceof FrameworkAgnosticDbImporter) {
            return static::wrap($resolved);
        }

        if (! $resolved instanceof DbImporter) {
            throw CannotCreateDbImporter::unsupportedDriver((string) ($config['driver'] ?? ''));
        }

        return $resolved;
    }

    /**
     * Presents a framework-agnostic importer under the deprecated interface
     * the rest of the package still calls.
     */
    protected static function wrap(FrameworkAgnosticDbImporter $importer): DbImporter
    {
        return new class($importer) extends DbImporter
        {
            public function __construct(protected FrameworkAgnosticDbImporter $importer) {}

            public function getCliName(): string
            {
                return $this->importer->getBinaryName();
            }

            public function getImportCommand(string $dumpFile, string $connection): string
            {
                return static::commandForDisplay($this->importer->getImportCommand());
            }

            public function importToDatabase(string $dumpFile, string $connection): void
            {
                $this->importer->importFromFile($dumpFile);

                event(new DatabaseDumpImportWasSuccessful($dumpFile));
            }
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected static function configure(FrameworkAgnosticDbImporter $importer, array $config): FrameworkAgnosticDbImporter
    {
        $importer->setDbName((string) ($config['database'] ?? ''));

        if ($importer instanceof SqliteImporter) {
            // The database name is the path to the file; there is nothing else.
            return $importer;
        }

        if (filled($config['username'] ?? null)) {
            $importer->setUserName((string) $config['username']);
        }

        if (filled($config['password'] ?? null)) {
            $importer->setPassword((string) $config['password']);
        }

        if (filled($config['host'] ?? null)) {
            $importer->setHost((string) Arr::first(Arr::wrap($config['host'])));
        }

        if (filled($config['port'] ?? null)) {
            $importer->setPort((int) $config['port']);
        }

        if (filled($config['unix_socket'] ?? null)) {
            $importer->setSocket((string) $config['unix_socket']);
        }

        if ($importer instanceof PostgreSqlImporter) {
            if (filled($config['search_path'] ?? null)) {
                $importer->setSearchPath(implode(',', Arr::wrap($config['search_path'])));
            }

            $importer->allowMetaCommands((bool) config('backup-restore.allow_psql_meta_commands', false));
        }

        if (filled($binaryPath = data_get($config, 'dump.dump_binary_path'))) {
            $importer->setImportBinaryPath((string) $binaryPath);
        }

        $importer->setExtraOptions(static::parseOptions((string) data_get($config, 'dump.options', '')));

        return $importer;
    }

    /**
     * spatie/laravel-backup stores the dump options as one string of CLI flags,
     * while the importers take an array. Split the string into arguments,
     * honouring quoted values that contain spaces.
     *
     * @return array<int, string>
     */
    public static function parseOptions(string $options): array
    {
        preg_match_all('/(?:[^\s"\']+|"[^"]*"|\'[^\']*\')+/', $options, $matches);

        return array_map(
            fn (string $option): string => preg_replace_callback(
                '/"([^"]*)"|\'([^\']*)\'/',
                fn (array $match): string => $match[2] ?? $match[1],
                $option
            ) ?? $option,
            $matches[0]
        );
    }
}
