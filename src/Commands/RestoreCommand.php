<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Wnx\LaravelBackupRestore\Actions\CleanupLocalBackupAction;
use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\Actions\ImportDumpAction;
use Wnx\LaravelBackupRestore\PendingRestore;

class RestoreCommand extends Command
{
    public $signature = 'backup:restore-db {--disk=} {--backup=} {--connection=} {--password=}';

    public $description = 'Restore a backup from a given disk.';

    public function handle(
        DownloadBackupAction $downloadBackupAction,
        DecompressBackupAction $decompressBackupAction,
        ImportDumpAction $importDumpAction,
        CleanupLocalBackupAction $cleanupLocalBackupAction
    ): int {
        $destination = $this->getDestinationDiskToRestoreFrom();
        $backup = $this->getBackupToRestore($destination);
        $connection = $this->option('connection') ?? config('backup.backup.source.databases')[0];

        // Ask for password if backup is encrypted
        // $password = $this->option('password') ?? $this->secret('What is the password?', null);

        if ($this->option('password')) {
            $password = $this->option('password');
        } else {
            $password = config('backup.backup.password');
        }

        $pendingRestore = PendingRestore::make(
            disk: $destination,
            backup: $backup,
            connection: $connection,
            backupPassword: $password,
        );

        $downloadBackupAction->execute($pendingRestore);
        $decompressBackupAction->execute($pendingRestore);

        // TODO: Use different "import dump" action depending on database type
        $importDumpAction->execute($pendingRestore);
        $cleanupLocalBackupAction->execute($pendingRestore);

        return self::SUCCESS;
    }

    private function getDestinationDiskToRestoreFrom(): string
    {
        // Use disk from --disk option if provided
        if ($this->option('disk')) {
            return $this->option('disk');
        }

        $availableDestinations = config('backup.backup.destination.disks');

        // If there is only one disk configured, use it
        if (count($availableDestinations) === 1) {
            return $availableDestinations[0];
        }

        // Ask user to choose a disk
        return $this->choice(
            'From which disk should the backup be restored?',
            $availableDestinations,
            head($availableDestinations)
        );
    }

    private function getBackupToRestore(string $disk): string
    {
        $name = config('backup.backup.name');

        $this->info("Fetch list of backups from {$disk} …");
        $listOfBackups = collect(Storage::disk($disk)->allFiles($name))
            ->filter(fn ($file) => Str::endsWith($file, '.zip'));

        if ($listOfBackups->count() === 0) {
            $this->error("No backups found on {$disk}.");
            // TODO: Throw an exception here
            exit(1);
        }

        if ($this->option('backup') === 'latest') {
            return $listOfBackups->last();
        }

        if ($this->option('backup')) {
            return $this->option('backup');
        }

        return $this->choice(
            'Which backup should be restored?',
            $listOfBackups->toArray(),
            $listOfBackups->last()
        );
    }
}
