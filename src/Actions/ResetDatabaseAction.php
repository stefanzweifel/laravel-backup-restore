<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\Events\DatabaseReset;
use Wnx\LaravelBackupRestore\PendingRestore;

class ResetDatabaseAction
{
    public function execute(PendingRestore $pendingRestore)
    {
        consoleOutput()->info('Reset database …');

        DB::connection($pendingRestore->connection)
            ->getSchemaBuilder()
            ->dropAllTables();

        event(new DatabaseReset($pendingRestore));
    }
}
