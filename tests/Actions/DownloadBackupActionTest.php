<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\PendingRestore;

test('it downloads backup from remote disk and stores it in local storage', function () {
    Storage::fake('local');

    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/2023-01-28-mysql-no-compression-no-encryption.zip',
        connection: 'mysql',
        backupPassword: 'password'
    );

    /** @var DownloadBackupAction $downloadBackupAction */
    $downloadBackupAction = app(DownloadBackupAction::class);

    $downloadBackupAction->execute($pendingRestore);

    expect(Storage::disk('local')->exists($pendingRestore->getPathToLocalCompressedBackup()))->toBeTrue();
});
