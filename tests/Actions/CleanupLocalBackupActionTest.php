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

it('deletes the downloaded archive as well as the extracted directory', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'local',
        backup: 'backup.zip',
        connection: 'sqlite',
    );

    Storage::disk('local')->put($pendingRestore->getPathToLocalCompressedBackup(), 'zip-contents');
    Storage::disk('local')->put($pendingRestore->getPathToLocalDecompressedBackup().'/db-dumps/dump.sql', 'SELECT 1;');

    (new CleanupLocalBackupAction)->execute($pendingRestore);

    expect(Storage::disk('local')->exists($pendingRestore->getPathToLocalCompressedBackup()))->toBeFalse();
    expect(Storage::disk('local')->exists($pendingRestore->getPathToLocalDecompressedBackup()))->toBeFalse();
});

it('does not fail when the archive was already removed', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'local',
        backup: 'backup.zip',
        connection: 'sqlite',
    );

    Storage::disk('local')->put($pendingRestore->getPathToLocalDecompressedBackup().'/db-dumps/dump.sql', 'SELECT 1;');

    (new CleanupLocalBackupAction)->execute($pendingRestore);

    expect(Storage::disk('local')->exists($pendingRestore->getPathToLocalDecompressedBackup()))->toBeFalse();
});
