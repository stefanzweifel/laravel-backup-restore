<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\DbImporterFactory;
use Wnx\LaravelBackupRestore\Events\DatabaseRestored;
use Wnx\LaravelBackupRestore\Exceptions\CannotCreateDbImporter;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;
use Wnx\LaravelBackupRestore\Exceptions\NoDatabaseDumpsFound;
use Wnx\LaravelBackupRestore\PendingRestore;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

class ImportDumpAction
{
    /**
     * @throws NoDatabaseDumpsFound
     * @throws CannotCreateDbImporter
     * @throws ImportFailed
     */
    public function execute(PendingRestore $pendingRestore): void
    {
        $dbDumps = $pendingRestore->getAvailableDbDumps();

        if ($dbDumps->isEmpty()) {
            throw NoDatabaseDumpsFound::notFoundInBackup($pendingRestore);
        }

        $importer = DbImporterFactory::createFromConnection($pendingRestore->connection);

        info('Importing database '.str('dump')->plural($dbDumps)->__toString().' …');

        $dbDumps->each(function ($dbDump) use ($pendingRestore, $importer) {
            spin(function () use ($importer, $dbDump, $pendingRestore) {
                $absolutePathToDump = Storage::disk($pendingRestore->restoreDisk)->path($dbDump);
                $importer->importToDatabase($absolutePathToDump, $pendingRestore->connection);
            }, message: 'Importing '.str($dbDump)->afterLast('/')->__toString());

            info('Imported '.str($dbDump)->afterLast('/')->__toString());
        });

        event(new DatabaseRestored($pendingRestore));
    }
}
