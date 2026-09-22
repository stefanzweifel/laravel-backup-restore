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
     * Encryption methods spatie/laravel-backup can write. Its Encryption enum maps
     * `default` and `aes256` to EM_AES_256, `aes128` to EM_AES_128 and `aes192` to
     * EM_AES_192. ZipCrypto (EM_TRAD_PKWARE) is not in that set and is broken, so
     * it is not accepted here either.
     *
     * @var array<int, int>
     */
    private const ACCEPTED_ENCRYPTION_METHODS = [
        ZipArchive::EM_AES_128,
        ZipArchive::EM_AES_192,
        ZipArchive::EM_AES_256,
    ];

    private const COPY_CHUNK_SIZE = 1024 * 1024;

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

        if ($result !== true) {
            throw DecompressionFailed::create($result, $pathToFileToDecompress);
        }

        if ($pendingRestore->backupPassword) {
            $zip->setPassword($pendingRestore->backupPassword);
        }

        try {
            $this->checkEntries($zip, $pathToFileToDecompress, (bool) $pendingRestore->backupPassword);
        } catch (DecompressionFailed $exception) {
            $zip->close();

            throw $exception;
        }

        spin(function () use ($pathToFileToDecompress, $extractTo, $zip) {
            try {
                $this->extract($zip, $extractTo, $pathToFileToDecompress);
            } finally {
                $zip->close();
            }
        }, 'Extracting database dump from backup …');

        info('Extracted database dump from backup.');

        Storage::disk($pendingRestore->restoreDisk)
            ->delete($pendingRestore->getPathToLocalCompressedBackup());
    }

    /**
     * @throws DecompressionFailed
     */
    private function checkEntries(ZipArchive $zip, string $archivePath, bool $passwordWasSupplied): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if ($entryName === false) {
                continue;
            }

            $this->validateZipEntry($entryName, $archivePath);

            if (! $passwordWasSupplied) {
                continue;
            }

            $stat = $zip->statIndex($i);

            // ZIP encryption is per entry, so an archive in which nothing is
            // encrypted extracts fine even when a password was supplied. Say so
            // instead of restoring an archive that is not the expected one.
            if ($stat !== false && ! in_array($stat['encryption_method'], self::ACCEPTED_ENCRYPTION_METHODS, true)) {
                throw DecompressionFailed::entryIsNotEncrypted($entryName, $archivePath);
            }
        }
    }

    /**
     * Write every entry out one by one instead of calling ZipArchive::extractTo().
     *
     * ZIP entry names are "/"-separated per spec, but archives written by some
     * Windows tooling contain "\". extractTo() does not treat those as directory
     * separators: an entry named "db-dumps\dump.sql" becomes a single file with a
     * backslash in its name, and the restore then finds no dumps (issue #81).
     *
     * Doing the writes here also keeps the decrypted dump off a world-readable
     * file, which extractTo() has no way to avoid.
     *
     * @throws DecompressionFailed
     */
    private function extract(ZipArchive $zip, string $extractTo, string $archivePath): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if ($entryName === false) {
                continue;
            }

            $relativePath = str_replace('\\', '/', $entryName);
            $target = $extractTo.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if (str_ends_with($relativePath, '/')) {
                $this->makeDirectory(rtrim($target, DIRECTORY_SEPARATOR), $archivePath);

                continue;
            }

            $this->makeDirectory(dirname($target), $archivePath);
            $this->writeEntry($zip, $i, $target, $archivePath);
        }
    }

    /**
     * @throws DecompressionFailed
     */
    private function writeEntry(ZipArchive $zip, int $index, string $target, string $archivePath): void
    {
        // Returns false for an encrypted entry when no or the wrong password was
        // set, and raises a warning while doing so.
        $source = @$zip->getStreamIndex($index);

        if ($source === false) {
            throw DecompressionFailed::create(false, $archivePath);
        }

        $destination = fopen($target, 'wb');

        if ($destination === false) {
            fclose($source);

            throw DecompressionFailed::create(false, $archivePath);
        }

        // The dump is plaintext once it is on disk. Narrow the mode while the file
        // is still empty, before anything is written to it.
        @chmod($target, 0600);

        try {
            while (! feof($source)) {
                $chunk = fread($source, self::COPY_CHUNK_SIZE);

                if ($chunk === false) {
                    throw DecompressionFailed::create(false, $archivePath);
                }

                if (fwrite($destination, $chunk) === false) {
                    throw DecompressionFailed::create(false, $archivePath);
                }
            }
        } finally {
            fclose($source);
            fclose($destination);
        }
    }

    /**
     * @throws DecompressionFailed
     */
    private function makeDirectory(string $directory, string $archivePath): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0700, recursive: true) && ! is_dir($directory)) {
            throw DecompressionFailed::create(false, $archivePath);
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
