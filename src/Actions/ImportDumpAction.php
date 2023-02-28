<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\DbImporterFactory;
use Wnx\LaravelBackupRestore\PendingRestore;

class ImportDumpAction
{
    public function execute(PendingRestore $pendingRestore)
    {
        if ($pendingRestore->hasNoDbDumpsDirectory()) {
            throw new \Exception('No DB Dumps found in Backup');
        }

        /** @var array<int, string> $dbDumps */
        $dbDumps = $pendingRestore->getAvailableDbDumps();

        foreach ($dbDumps as $dbDump) {
            // Create Absolute Path
            $storagePathToDatabaseFile = Storage::disk($pendingRestore->restoreDisk)->path($dbDump);

            $importer = DbImporterFactory::createFromConnection($pendingRestore->connection);
            $importer->importToDatabase($storagePathToDatabaseFile);
        }
    }
}
