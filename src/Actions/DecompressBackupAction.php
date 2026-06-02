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

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if ($entryName !== false) {
                    $this->validateZipEntry($entryName, $pathToFileToDecompress);
                }
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

    /**
     * @throws DecompressionFailed
     */
    private function validateZipEntry(string $entryName, string $archivePath): void
    {
        // Reject null bytes, POSIX absolute paths, and Windows absolute paths
        if (str_contains($entryName, "\0")
            || str_starts_with($entryName, '/')
            || str_starts_with($entryName, '\\')
            || preg_match('/^[A-Za-z]:/', $entryName) === 1
        ) {
            throw DecompressionFailed::pathTraversalDetected($entryName, $archivePath);
        }

        // Reject ".." segments that would escape the extraction root
        $depth = 0;
        foreach (preg_split('#[/\\\\]#', $entryName) as $segment) {
            if ($segment === '..') {
                $depth--;
                if ($depth < 0) {
                    throw DecompressionFailed::pathTraversalDetected($entryName, $archivePath);
                }
            } elseif ($segment !== '.' && $segment !== '') {
                $depth++;
            }
        }
    }
}
