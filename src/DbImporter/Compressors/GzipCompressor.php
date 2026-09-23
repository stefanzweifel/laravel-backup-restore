<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Compressors;

use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotStartImport;

class GzipCompressor implements Compressor
{
    public function usedExtension(): string
    {
        return 'gz';
    }

    public function magicBytes(): ?string
    {
        return "\x1f\x8b";
    }

    /**
     * `compress.zlib://` reads the gzip container, not only a raw deflate
     * stream, so no separate gzip wrapper is needed.
     */
    public function open(string $dumpFile)
    {
        if (! extension_loaded('zlib')) {
            throw CannotStartImport::missingExtension('zlib', $dumpFile);
        }

        $stream = @fopen('compress.zlib://'.$dumpFile, 'rb');

        if ($stream === false) {
            throw CannotStartImport::dumpFileNotReadable($dumpFile);
        }

        return $stream;
    }
}
