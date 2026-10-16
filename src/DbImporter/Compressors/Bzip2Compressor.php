<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Compressors;

use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotStartImport;

class Bzip2Compressor implements Compressor
{
    public function usedExtension(): string
    {
        return 'bz2';
    }

    public function magicBytes(): ?string
    {
        return 'BZh';
    }

    public function open(string $dumpFile)
    {
        if (! extension_loaded('bz2')) {
            throw CannotStartImport::missingExtension('bz2', $dumpFile);
        }

        $stream = @fopen('compress.bzip2://'.$dumpFile, 'rb');

        if ($stream === false) {
            throw CannotStartImport::dumpFileNotReadable($dumpFile);
        }

        return $stream;
    }
}
