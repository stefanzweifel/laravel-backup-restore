<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\HealthChecks;

use Wnx\LaravelBackupRestore\PendingRestore;

abstract class HealthCheck
{
    abstract public function run(PendingRestore $pendingRestore): Result;

    public static function new(): self
    {
        return app(static::class);
    }
}
