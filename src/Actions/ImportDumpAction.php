<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\DbImporterFactory;
use Wnx\LaravelBackupRestore\Exceptions\CannotCreateDbImporter;
use Wnx\LaravelBackupRestore\Exceptions\NoDatabaseDumpsFound;
use Wnx\LaravelBackupRestore\PendingRestore;

class ImportDumpAction
{
    /**
     * @throws NoDatabaseDumpsFound
     * @throws CannotCreateDbImporter
     */
    public function execute(PendingRestore $pendingRestore): void
    {
        if ($pendingRestore->hasNoDbDumpsDirectory()) {
            throw NoDatabaseDumpsFound::notFoundInBackup($pendingRestore);
        }

        $importer = DbImporterFactory::createFromConnection($pendingRestore->connection);

        /** @var array<int, string> $dbDumps */
        $dbDumps = $pendingRestore->getAvailableDbDumps();

        consoleOutput()->info('Importing database '.str('dump')->plural($dbDumps)->__toString().' …');

        foreach ($dbDumps as $dbDump) {
            consoleOutput()->info('Importing '.str($dbDump)->afterLast('/')->__toString());
            $absolutePathToDump = Storage::disk($pendingRestore->restoreDisk)->path($dbDump);
            $importer->importToDatabase($absolutePathToDump);
        }
    }
}
