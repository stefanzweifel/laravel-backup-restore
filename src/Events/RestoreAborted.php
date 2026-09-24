<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Events;

use Wnx\LaravelBackupRestore\PendingRestore;

class RestoreAborted
{
    public function __construct(
        public readonly PendingRestore $pendingRestore,
        public readonly int $signal,
        public readonly bool $databaseWasTouched,
    ) {
        //
    }
}
