<?php

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\PendingRestore;

class DownloadBackupAction
{
    public function execute(PendingRestore $pendingRestore)
    {
        Storage::disk($pendingRestore->restoreDisk)
            ->writeStream(
                $pendingRestore->getPathToLocalCompressedBackup(),
                Storage::disk($pendingRestore->disk)->readStream($pendingRestore->backup)
            );
    }
}
