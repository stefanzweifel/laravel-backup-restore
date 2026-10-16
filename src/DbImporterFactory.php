<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Wnx\LaravelBackupRestore\Databases\DbImporter;
use Wnx\LaravelBackupRestore\Databases\MariaDb;
use Wnx\LaravelBackupRestore\Databases\MySql;
use Wnx\LaravelBackupRestore\Databases\PostgreSql;
use Wnx\LaravelBackupRestore\Databases\Sqlite;
use Wnx\LaravelBackupRestore\DbImporter\Databases\PostgreSql as PostgreSqlImporter;
use Wnx\LaravelBackupRestore\DbImporter\Databases\Sqlite as SqliteImporter;
use Wnx\LaravelBackupRestore\DbImporter\DbImporter as FrameworkAgnosticDbImporter;
use Wnx\LaravelBackupRestore\DbImporter\DbImporterFactory as ImporterForDriver;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotStartImport;
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
            static::stringFrom(static::configFor($dbConnectionName)['driver'] ?? null),
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
        $driver = Str::lower(static::stringFrom($config['driver'] ?? null));

        try {
            $importer = ImporterForDriver::forDriver($driver);
        } catch (CannotStartImport) {
            throw CannotCreateDbImporter::unsupportedDriver($driver);
        }

        return static::configure($importer, $config, $dbConnectionName);
    }

    /**
     * The directory the database client is called from, or an empty string when
     * it is expected on the PATH. Both the importer and CheckDependenciesAction
     * read this, so they always agree on which binary has to exist.
     */
    public static function binaryPathForConnection(string $dbConnectionName): string
    {
        $configured = config('backup-restore.import_binary_path', '');

        $path = is_array($configured)
            ? static::stringFrom($configured[$dbConnectionName] ?? null)
            : static::stringFrom($configured);

        if ($path !== '') {
            return $path;
        }

        // Deprecated: dump.dump_binary_path is spatie/laravel-backup's path to
        // the dump binaries. It was the only way to point this package at a
        // client before backup-restore.import_binary_path existed.
        return static::stringFrom(config("database.connections.{$dbConnectionName}.dump.dump_binary_path"));
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

        if (! is_array($config) && ! is_string($config)) {
            throw CannotCreateDbImporter::configNotFound($dbConnectionName);
        }

        if (is_array($config)) {
            // config() hands back an untyped array. The parser, and everything
            // that reads the result, index it by name.
            $config = array_combine(
                array_map(strval(...), array_keys($config)),
                $config
            );
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
            throw CannotCreateDbImporter::unsupportedDriver(static::stringFrom($config['driver'] ?? null));
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
                return self::commandForDisplay($this->importer->getImportCommand());
            }

            protected function forwardsToDbImporter(): bool
            {
                return true;
            }

            protected function resolveImporter(string $connection): FrameworkAgnosticDbImporter
            {
                return $this->importer;
            }
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected static function configure(FrameworkAgnosticDbImporter $importer, array $config, string $dbConnectionName): FrameworkAgnosticDbImporter
    {
        $importer->setDbName(static::stringFrom($config['database'] ?? null));

        if ($importer instanceof SqliteImporter) {
            // The database name is the path to the file; there is nothing else.
            return $importer;
        }

        if (filled($config['username'] ?? null)) {
            $importer->setUserName(static::stringFrom($config['username']));
        }

        if (filled($config['password'] ?? null)) {
            $importer->setPassword(static::stringFrom($config['password']));
        }

        if (filled($config['host'] ?? null)) {
            $importer->setHost(static::stringFrom(Arr::first(Arr::wrap($config['host']))));
        }

        if (filled($config['port'] ?? null)) {
            $importer->setPort(static::intFrom($config['port']));
        }

        if (filled($config['unix_socket'] ?? null)) {
            $importer->setSocket(static::stringFrom($config['unix_socket']));
        }

        if ($importer instanceof PostgreSqlImporter) {
            if (filled($config['search_path'] ?? null)) {
                $importer->setSearchPath(implode(',', array_map(static::stringFrom(...), Arr::wrap($config['search_path']))));
            }

            $importer->allowMetaCommands(Config::boolean('backup-restore.allow_psql_meta_commands', false));
        }

        if (filled($binaryPath = static::binaryPathForConnection($dbConnectionName))) {
            $importer->setImportBinaryPath($binaryPath);
        }

        $importer->setExtraOptions(static::parseOptions(static::stringFrom(data_get($config, 'dump.options', ''))));

        return $importer;
    }

    /**
     * Connection config is user-supplied and untyped. Coerce the scalars the
     * importers need and fall back to the default for anything else.
     */
    protected static function stringFrom(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    protected static function intFrom(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
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
