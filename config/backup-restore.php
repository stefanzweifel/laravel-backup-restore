<?php

declare(strict_types=1);

return [

    /*
     * Health checks are run after the backup has been restored.
     * The restore command will fail, if any health checks fail.
     */
    'health-checks' => [
        \Wnx\LaravelBackupRestore\HealthChecks\DatabaseHasTables::class,
    ],
];
