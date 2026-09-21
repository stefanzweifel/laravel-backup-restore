<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Compressors;

use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotStartImport;

class CompressorFactory
{
    /**
     * spatie/laravel-backup lets the dump file extension be configured, so a
     * gzipped dump can be called anything. The magic number decides; the
     * extension is only consulted when the file is too short to have one.
     */
    public static function forDumpFile(string $dumpFile): Compressor
    {
        return static::fromMagicBytes($dumpFile)
            ?? static::fromExtension($dumpFile)
            ?? new NullCompressor;
    }

    /**
     * @return array<int, Compressor>
     */
    public static function compressors(): array
    {
        return [
            new GzipCompressor,
            new Bzip2Compressor,
        ];
    }

    protected static function fromMagicBytes(string $dumpFile): ?Compressor
    {
        $stream = @fopen($dumpFile, 'rb');

        if ($stream === false) {
            throw CannotStartImport::dumpFileNotReadable($dumpFile);
        }

        $header = (string) fread($stream, 8);
        fclose($stream);

        foreach (static::compressors() as $compressor) {
            $magicBytes = $compressor->magicBytes();

            if ($magicBytes !== null && str_starts_with($header, $magicBytes)) {
                return $compressor;
            }
        }

        return null;
    }

    protected static function fromExtension(string $dumpFile): ?Compressor
    {
        $extension = strtolower(pathinfo($dumpFile, PATHINFO_EXTENSION));

        foreach (static::compressors() as $compressor) {
            if ($compressor->usedExtension() !== '' && $compressor->usedExtension() === $extension) {
                return $compressor;
            }
        }

        return null;
    }
}
