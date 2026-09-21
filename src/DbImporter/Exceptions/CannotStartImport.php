<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Exceptions;

use RuntimeException;

class CannotStartImport extends RuntimeException
{
    public static function create(string $message): self
    {
        return new self($message);
    }

    public static function dumpFileNotReadable(string $dumpFile): self
    {
        return new self("The dump file `{$dumpFile}` does not exist or is not readable.");
    }

    public static function missingExtension(string $extension, string $dumpFile): self
    {
        return new self("The dump file `{$dumpFile}` is compressed with {$extension}, but the PHP extension `ext-{$extension}` is not loaded.");
    }
}
