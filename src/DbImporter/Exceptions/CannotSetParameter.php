<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Exceptions;

use InvalidArgumentException;

class CannotSetParameter extends InvalidArgumentException
{
    public static function mustNotBeNegative(string $name, int $value): self
    {
        return new self("`{$name}` must not be negative, got {$value}.");
    }

    public static function mustNotBeEmpty(string $name): self
    {
        return new self("`{$name}` must not be an empty string.");
    }
}
