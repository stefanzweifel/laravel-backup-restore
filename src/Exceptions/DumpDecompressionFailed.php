<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;

class DumpDecompressionFailed extends Exception
{
    public static function create(string $message, string $filename): static
    {
        return new static("Could not decompress $filename dump file: $message");
    }
}
