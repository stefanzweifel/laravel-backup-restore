<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Databases\MySql;
use Wnx\LaravelBackupRestore\PendingRestore;

class ImportMySqlDumpAction
{
    public function __construct(readonly public MySql $mySql)
    {
        //
    }

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

            $this->mySql->importToDatabase($storagePathToDatabaseFile);
        }
    }
}
