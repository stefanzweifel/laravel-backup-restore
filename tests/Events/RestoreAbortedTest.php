<?php

declare(strict_types=1);

use Wnx\LaravelBackupRestore\Events\RestoreAborted;
use Wnx\LaravelBackupRestore\PendingRestore;

it('carries the restore, the signal and the database state', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'local',
        backup: 'backup.zip',
        connection: 'sqlite',
    );

    $event = new RestoreAborted($pendingRestore, 15, databaseWasTouched: true);

    expect($event->pendingRestore)->toBe($pendingRestore);
    expect($event->signal)->toBe(15);
    expect($event->databaseWasTouched)->toBeTrue();
});
