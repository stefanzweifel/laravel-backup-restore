<?php

declare(strict_types=1);
use Wnx\LaravelBackupRestore\HealthChecks\Checks\DatabaseHasTables;

return [

    /**
     * Health checks are run after a given backup has been restored.
     * With health checks, you can make sure that the restored database contains the data you expect.
     * By default, we check if the restored database contains any tables.
     *
     * You can add your own health checks by adding a class that extends the HealthCheck class.
     * The restore command will fail, if any health checks fail.
     */
    'health-checks' => [
        DatabaseHasTables::class,
    ],

    /**
     * psql executes backslash meta-commands it reads from a dump, and `\!` runs
     * a shell command. Dumps are therefore scanned and refused if they contain
     * a meta-command that pg_dump does not itself emit.
     *
     * Set this to true to hand meta-commands to psql anyway. Only do that for
     * dumps you trust.
     */
    'allow_psql_meta_commands' => false,

    /**
     * The directory holding the database clients used to import a dump: mysql,
     * mariadb, psql and pg_restore. Leave this empty when they are on the PATH
     * of the PHP process, which is the usual case.
     *
     * Set one path for every connection:
     *
     *     'import_binary_path' => '/opt/homebrew/opt/mysql-client/bin',
     *
     * or one per connection, when the clients live in different places:
     *
     *     'import_binary_path' => [
     *         'mysql' => '/opt/homebrew/opt/mysql-client/bin',
     *         'pgsql' => '/Applications/Postgres.app/Contents/Versions/17/bin',
     *     ],
     *
     * When this is empty, the connection's dump.dump_binary_path in
     * config/database.php is used instead. That key belongs to
     * spatie/laravel-backup and points at the dump binaries rather than the
     * import ones; reading it is deprecated and will stop in the next major
     * version.
     */
    'import_binary_path' => env('BACKUP_RESTORE_IMPORT_BINARY_PATH', ''),
];
