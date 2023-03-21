<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

class Sqlite extends DbImporter
{
    public function getImportCommand(string $dumpFile): string
    {
        // @todo: Improve detection of compressed files
        // @todo: Use $pendingRestore->connection
        if (str($dumpFile)->endsWith('gz')) {
            // Shell command to import a gzipped SQL file to a sqlite database
            return 'gunzip -c '.$dumpFile.' | sqlite3 '.config('database.connections.sqlite.database');
        }

        return 'sqlite3 '.config('database.connections.sqlite.database').' < '.$dumpFile;
    }
}
