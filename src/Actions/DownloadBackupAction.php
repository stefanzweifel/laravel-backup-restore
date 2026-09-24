<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Exceptions\CannotOpenBackup;
use Wnx\LaravelBackupRestore\PendingRestore;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

class DownloadBackupAction
{
    /**
     * @throws CannotOpenBackup|\Throwable
     */
    public function execute(PendingRestore $pendingRestore): void
    {
        spin(function () use ($pendingRestore) {
            $stream = Storage::disk($pendingRestore->disk)->readStream($pendingRestore->backup);

            throw_if($stream === null, CannotOpenBackup::onDisk($pendingRestore->disk, $pendingRestore->backup));

            Storage::disk($pendingRestore->restoreDisk)
                ->writeStream(
                    $pendingRestore->getPathToLocalCompressedBackup(),
                    $stream,
                    // The local adapter only chmods a written file when the write
                    // carries this option. Without it the archive lands at 0644.
                    ['visibility' => 'private']
                );
        }, "Downloading {$pendingRestore->backup}");

        info("Backup downloaded to {$pendingRestore->getPathToLocalCompressedBackup()}.");
    }
}
