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
                    Storage::disk($pendingRestore->disk)->readStream($pendingRestore->backup),
                    // The local adapter only chmods a written file when the write
                    // carries this option. Without it the archive lands at 0644.
                    ['visibility' => 'private']
                );
        }, "Downloading {$pendingRestore->backup}");

        info("Backup downloaded to {$pendingRestore->getPathToLocalCompressedBackup()}.");
    }
}
