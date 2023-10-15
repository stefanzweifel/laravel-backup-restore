<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Exceptions\DecompressionFailed;
use Wnx\LaravelBackupRestore\PendingRestore;
use ZipArchive;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

class DecompressBackupAction
{
    /**
     * @throws DecompressionFailed
     */
    public function execute(PendingRestore $pendingRestore): void
    {
        $extractTo = $pendingRestore->getAbsolutePathToLocalDecompressedBackup();

        $pathToFileToDecompress = Storage::disk($pendingRestore->restoreDisk)
            ->path($pendingRestore->getPathToLocalCompressedBackup());

        $zip = new ZipArchive;
        $result = $zip->open($pathToFileToDecompress);

        if ($result === true) {
            if ($pendingRestore->backupPassword) {
                $zip->setPassword($pendingRestore->backupPassword);
            }

            spin(function () use ($pathToFileToDecompress, $extractTo, $zip) {
                $extractionResult = $zip->extractTo($extractTo);
                $zip->close();

                if ($extractionResult === false) {
                    throw DecompressionFailed::create($extractionResult, $pathToFileToDecompress);
                }
            }, 'Extracting database dump from backup …');

            info('Extracted database dump from backup.');
        } else {
            throw DecompressionFailed::create($result, $pathToFileToDecompress);
        }
    }
}
