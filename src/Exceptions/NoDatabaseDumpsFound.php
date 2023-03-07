<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Wnx\LaravelBackupRestore\PendingRestore;

class NoDatabaseDumpsFound extends \Exception
{
    public static function notFoundInBackup(PendingRestore $pendingRestore): self
    {
        return new static("No database dumps found in backup `{$pendingRestore->backup}`.");
    }
}
