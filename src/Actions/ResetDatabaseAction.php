<?php

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\PendingRestore;

class ResetDatabaseAction
{
    public function execute(PendingRestore $pendingRestore)
    {
        consoleOutput()->info('Reset database …');

        DB::connection($pendingRestore->connection)
            ->getSchemaBuilder()
            ->dropAllTables();
    }
}
