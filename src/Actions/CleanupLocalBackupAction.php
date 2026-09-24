<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Events\LocalBackupRemoved;
use Wnx\LaravelBackupRestore\PendingRestore;

class CleanupLocalBackupAction
{
    public function execute(PendingRestore $pendingRestore): void
    {
        // The archive sits next to the extracted directory rather than inside it,
        // so deleteDirectory() does not reach it. DecompressBackupAction removes it
        // after a successful extraction; this covers every other way out.
        Storage::disk($pendingRestore->restoreDisk)
            ->delete($pendingRestore->getPathToLocalCompressedBackup());

        Storage::disk($pendingRestore->restoreDisk)
            ->deleteDirectory($pendingRestore->getPathToLocalDecompressedBackup());

        event(new LocalBackupRemoved($pendingRestore));
    }
}
