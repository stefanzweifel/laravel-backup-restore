<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\HealthChecks\Checks;

use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\HealthChecks\HealthCheck;
use Wnx\LaravelBackupRestore\HealthChecks\Result;
use Wnx\LaravelBackupRestore\PendingRestore;

class DatabaseHasTables extends HealthCheck
{
    public function run(PendingRestore $pendingRestore): Result
    {
        $result = Result::make($this);

        $connection = DB::connection($pendingRestore->connection);
        $schemaBuilder = $connection->getSchemaBuilder();

        $driverName = $connection->getDriverName();
        $tables = $schemaBuilder->getTables($driverName === 'mysql' ? $connection->getDatabaseName() : null);

        if (count($tables) === 0) {
            return $result->failed('Database has not tables after restore.');
        }

        return $result->ok();
    }
}
