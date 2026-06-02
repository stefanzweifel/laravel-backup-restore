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

it('throws DecompressionFailed when zip contains a path traversal entry using dot-dot segments', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/crafted-traversal.zip',
        connection: 'mysql',
    );

    $tmpPath = tempnam(sys_get_temp_dir(), 'lbr-test-').'.zip';
    $zip = new ZipArchive;
    $zip->open($tmpPath, ZipArchive::CREATE);
    $zip->addFromString('db-dumps/legitimate.sql', '-- harmless SQL');
    $zip->addFromString('../../../crafted-outside.sql', '-- evil');
    $zip->close();

    Storage::disk('local')->put(
        $pendingRestore->getPathToLocalCompressedBackup(),
        file_get_contents($tmpPath)
    );
    unlink($tmpPath);

    app(DecompressBackupAction::class)->execute($pendingRestore);
})
    ->throws(DecompressionFailed::class)
    ->expectExceptionMessage('path traversal');

it('throws DecompressionFailed when zip contains an entry with dot-dot that escapes after a real directory', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/crafted-traversal.zip',
        connection: 'mysql',
    );

    $tmpPath = tempnam(sys_get_temp_dir(), 'lbr-test-').'.zip';
    $zip = new ZipArchive;
    $zip->open($tmpPath, ZipArchive::CREATE);
    $zip->addFromString('db-dumps/../../outside.sql', '-- evil');
    $zip->close();

    Storage::disk('local')->put(
        $pendingRestore->getPathToLocalCompressedBackup(),
        file_get_contents($tmpPath)
    );
    unlink($tmpPath);

    app(DecompressBackupAction::class)->execute($pendingRestore);
})
    ->throws(DecompressionFailed::class)
    ->expectExceptionMessage('path traversal');

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
