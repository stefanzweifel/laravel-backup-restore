<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Tests\Support;

use Wnx\LaravelBackupRestore\HealthChecks\HealthCheck;
use Wnx\LaravelBackupRestore\HealthChecks\Result;
use Wnx\LaravelBackupRestore\PendingRestore;

class FailsWithoutMessage extends HealthCheck
{
    public function run(PendingRestore $pendingRestore): Result
    {
        return Result::make($this)->failed();
    }
}
