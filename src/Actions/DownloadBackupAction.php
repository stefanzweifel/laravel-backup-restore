<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\PendingRestore;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

class DownloadBackupAction
{
    public function execute(PendingRestore $pendingRestore): void
    {
        spin(function () use ($pendingRestore) {
            Storage::disk($pendingRestore->restoreDisk)
                ->writeStream(
                    $pendingRestore->getPathToLocalCompressedBackup(),
                    Storage::disk($pendingRestore->disk)->readStream($pendingRestore->backup)
                );
        }, "Downloading {$pendingRestore->backup}");

        info("Backup downloaded to {$pendingRestore->getPathToLocalCompressedBackup()}.");
    }
}
