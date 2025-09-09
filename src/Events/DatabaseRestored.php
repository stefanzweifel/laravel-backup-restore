<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Events;

use Wnx\LaravelBackupRestore\PendingRestore;

class DatabaseRestored
{
    public function __construct(public readonly PendingRestore $pendingRestore) {}
}
