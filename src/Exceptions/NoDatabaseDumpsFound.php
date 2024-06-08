<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;
use Wnx\LaravelBackupRestore\PendingRestore;

class NoDatabaseDumpsFound extends Exception
{
    public static function notFoundInBackup(PendingRestore $pendingRestore): self
    {

        $files = $pendingRestore->getAvailableFilesInDbDumpsDirectory()->implode("\n");

        return new static(<<<TXT
            "No database dumps found in backup `{$pendingRestore->backup}`."
            "Found files in db-dumps directory:"
            $files
        TXT
        );
    }
}
