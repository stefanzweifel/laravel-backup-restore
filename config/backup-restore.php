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
];
