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

        if (method_exists($schemaBuilder, 'getTables')) {
            $driverName = $connection->getDriverName();
            $tables = $schemaBuilder->getTables($driverName === 'mysql' ? $connection->getDatabaseName() : null);
        } else {
            // `getAllTables()` has been removed in Laravel 11.
            /** @phpstan-ignore-next-line  */
            $tables = $schemaBuilder->getAllTables();
        }

        if (count($tables) === 0) {
            return $result->failed('Database has not tables after restore.');
        }

        return $result->ok();
    }
}
