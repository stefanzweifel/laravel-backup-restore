<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;

class CliNotFound extends Exception
{
    public static function create(string $cli): self
    {
        return new static("CLI $cli not found. Please ensure $cli is in the PATH and available to your PHP process.");
    }
}
