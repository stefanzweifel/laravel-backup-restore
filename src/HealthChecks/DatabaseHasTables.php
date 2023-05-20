<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\HealthChecks;

use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\PendingRestore;

class DatabaseHasTables extends HealthCheck
{
    public function run(PendingRestore $pendingRestore): Result
    {
        $result = Result::make($this);

        $tables = DB::connection($pendingRestore->connection)
            ->getSchemaBuilder()
            ->getAllTables();

        if (count($tables) === 0) {
            return $result->failed('Database has not tables after restore.');
        }

        return $result->ok();
    }
}
