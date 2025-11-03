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

            spin(function () use ($pathToFileToDecompress, $extractTo, $zip, $pendingRestore) {
                if ($pendingRestore->onlyDb) {
                    // Extract only the db-dumps directory
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        if (str_starts_with($filename, 'db-dumps/')) {
                            $zip->extractTo($extractTo, $filename);
                        }
                    }
                } else {
                    // Extract everything
                    $extractionResult = $zip->extractTo($extractTo);
                    if ($extractionResult === false) {
                        throw DecompressionFailed::create($extractionResult, $pathToFileToDecompress);
                    }
                }
                $zip->close();
            }, 'Extracting database dump from backup …');

            info('Extracted database dump from backup.');
        } else {
            throw DecompressionFailed::create($result, $pathToFileToDecompress);
        }
    }
}
