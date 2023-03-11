<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction as DecompressBackupActionAlias;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\PendingRestore;

it('decompresses zip backup file without password', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/2023-01-28-mysql-no-compression-no-encryption.zip',
        connection: 'mysql',
        backupPassword: null,
    );

    Storage::assertMissing($pendingRestore->getPathToLocalCompressedBackup());
    app(DownloadBackupAction::class)->execute($pendingRestore);
    Storage::assertExists($pendingRestore->getPathToLocalCompressedBackup());

    Storage::assertMissing($pendingRestore->getPathToLocalDecompressedBackup());
    app(DecompressBackupActionAlias::class)->execute($pendingRestore);
    Storage::assertExists($pendingRestore->getPathToLocalDecompressedBackup());
});

test('decompresses zip backup file that needs password do decrypt', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/2023-01-28-mysql-no-compression-encrypted.zip',
        connection: 'mysql',
        backupPassword: 'password',
    );

    Storage::assertMissing($pendingRestore->getPathToLocalCompressedBackup());
    app(DownloadBackupAction::class)->execute($pendingRestore);
    Storage::assertExists($pendingRestore->getPathToLocalCompressedBackup());

    Storage::assertMissing($pendingRestore->getPathToLocalDecompressedBackup());
    app(DecompressBackupActionAlias::class)->execute($pendingRestore);
    Storage::assertExists($pendingRestore->getPathToLocalDecompressedBackup());
});
