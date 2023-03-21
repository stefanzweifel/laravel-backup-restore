<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\PendingRestore;

class CleanupLocalBackupAction
{
    public function execute(PendingRestore $pendingRestore): void
    {
        Storage::disk($pendingRestore->restoreDisk)
            ->delete($pendingRestore->getPathToLocalCompressedBackup());

        Storage::disk($pendingRestore->restoreDisk)
            ->deleteDirectory($pendingRestore->getPathToLocalDecompressedBackup());
    }
}
