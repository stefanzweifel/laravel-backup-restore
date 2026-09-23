<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Compressors;

use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotStartImport;

/**
 * Reads an uncompressed dump. The fallback when no compression is detected.
 */
class NullCompressor implements Compressor
{
    public function usedExtension(): string
    {
        return '';
    }

    public function magicBytes(): ?string
    {
        return null;
    }

    public function open(string $dumpFile)
    {
        $stream = @fopen($dumpFile, 'rb');

        if ($stream === false) {
            throw CannotStartImport::dumpFileNotReadable($dumpFile);
        }

        return $stream;
    }
}
