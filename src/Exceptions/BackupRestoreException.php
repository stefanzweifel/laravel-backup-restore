<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Throwable;

/**
 * Implemented by every exception this package throws, so that callers can
 * catch all of them with a single catch block.
 */
interface BackupRestoreException extends Throwable
{
    /**
     * An actionable next step for the user, or null if there is none.
     */
    public function hint(): ?string;
}
