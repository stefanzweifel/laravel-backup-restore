<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Exceptions;

use Exception;

/**
 * The import was interrupted by a terminal signal rather than failing on its
 * own. Translated into Wnx\LaravelBackupRestore\Exceptions\RestoreWasAborted at
 * the boundary in Wnx\LaravelBackupRestore\Databases\DbImporter.
 */
class ImportAborted extends Exception
{
    protected function __construct(
        public readonly int $signal,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function bySignal(int $signal): self
    {
        return new self(
            signal: $signal,
            message: "The import was interrupted by signal {$signal}.",
        );
    }
}
