<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\Exceptions\DecompressionFailed;
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
    app(DecompressBackupAction::class)->execute($pendingRestore);
    Storage::assertExists($pendingRestore->getPathToLocalDecompressedBackup());
});

it('decompresses zip backup file without password if local root differs from default', function () {
    config(['filesystems.disks.local.root' => storage_path('app/private')]);

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
    app(DecompressBackupAction::class)->execute($pendingRestore);
    Storage::assertExists('private'.DIRECTORY_SEPARATOR.$pendingRestore->getPathToLocalDecompressedBackup());
});

it('decompresses zip backup file that needs password do decrypt', function () {
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
    app(DecompressBackupAction::class)->execute($pendingRestore);
    Storage::assertExists($pendingRestore->getPathToLocalDecompressedBackup());
});

it('throws DecompressionFailed exception', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/not-a-zip-file.zip',
        connection: 'mysql',
        backupPassword: 'wrong-password',
    );

    app(DownloadBackupAction::class)->execute($pendingRestore);
    app(DecompressBackupAction::class)->execute($pendingRestore);
})
    ->throws(DecompressionFailed::class)
    ->expectExceptionMessage('Not a zip archive. (ZipArchive::ER_NOZIP)');

it('throws exception if backup password is wrong', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/2023-01-28-mysql-no-compression-encrypted.zip',
        connection: 'mysql',
        backupPassword: 'wrong-password',
    );

    app(DownloadBackupAction::class)->execute($pendingRestore);
    app(DecompressBackupAction::class)->execute($pendingRestore);
    Storage::assertMissing($pendingRestore->getPathToLocalDecompressedBackup());
})
    ->throws(DecompressionFailed::class);
