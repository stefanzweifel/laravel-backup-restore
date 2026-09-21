<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Compressors;

interface Compressor
{
    /**
     * The file extension this compressor is conventionally used with, without
     * the leading dot. An empty string means "no extension of its own".
     */
    public function usedExtension(): string;

    /**
     * The bytes a file of this format starts with, or null when the format has
     * no magic number.
     */
    public function magicBytes(): ?string;

    /**
     * Open the dump for reading. The returned stream yields the decompressed
     * contents.
     *
     * @return resource
     */
    public function open(string $dumpFile);
}
