<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Actions\CleanupLocalBackupAction;
use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\PendingRestore;

it('removes downloaded compressed and decompressed backup files', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/2023-01-28-mysql-no-compression-no-encryption.zip',
        connection: 'mysql',
        backupPassword: null,
    );

    // Download and decompress backup
    Storage::assertMissing($pendingRestore->getPathToLocalCompressedBackup());
    Storage::assertMissing($pendingRestore->getPathToLocalDecompressedBackup());
    app(DownloadBackupAction::class)->execute($pendingRestore);
    app(DecompressBackupAction::class)->execute($pendingRestore);

    // Cleanup any downloaded backups
    app(CleanupLocalBackupAction::class)->execute($pendingRestore);
    Storage::assertMissing($pendingRestore->getPathToLocalCompressedBackup());
    Storage::assertMissing($pendingRestore->getPathToLocalDecompressedBackup());
});
