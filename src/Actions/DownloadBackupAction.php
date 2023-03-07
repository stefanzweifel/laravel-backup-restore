<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\PendingRestore;

class DownloadBackupAction
{
    public function execute(PendingRestore $pendingRestore)
    {
        consoleOutput()->info('Downloading backup …');

        Storage::disk($pendingRestore->restoreDisk)
            ->writeStream(
                $pendingRestore->getPathToLocalCompressedBackup(),
                Storage::disk($pendingRestore->disk)->readStream($pendingRestore->backup)
            );
    }
}
