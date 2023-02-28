<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\PendingRestore;
use ZipArchive;

class DecompressBackupAction
{
    public function execute(PendingRestore $pendingRestore)
    {
        // Decompress given backup to given local path
        $extractTo = $pendingRestore->getAbsolutePathToLocalDecompressedBackup();

        // Unzip Backup File with Encryption Key
        $zip = new ZipArchive;

        $pathToFileToDecompress = Storage::disk($pendingRestore->restoreDisk)
                ->path($pendingRestore->getPathToLocalCompressedBackup());

        $result = $zip->open($pathToFileToDecompress);

        if ($result === true) {
            if ($pendingRestore->backupPassword) {
                $zip->setPassword($pendingRestore->backupPassword);
            }

            $zip->extractTo($extractTo);
            $zip->close();
        } else {
            // TODO: Throw Exception
            dd('Failed to unzip encrypted backup', $result, $pendingRestore);
        }
    }
}
