<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\Exceptions\DecompressionFailed;
use Wnx\LaravelBackupRestore\Exceptions\RestoreWasAborted;
use Wnx\LaravelBackupRestore\PendingRestore;
use Wnx\LaravelBackupRestore\RestoreAbort;

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
    Storage::assertMissing($pendingRestore->getPathToLocalCompressedBackup());
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
    Storage::assertMissing($pendingRestore->getPathToLocalCompressedBackup());
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

it('extracts entries from an archive written on Windows into directories', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/windows-authored.zip',
        connection: 'mysql',
    );

    // ZIP entry names are /-separated per spec, but some Windows tooling writes \.
    $tmpPath = tempnam(sys_get_temp_dir(), 'lbr-test-').'.zip';
    $zip = new ZipArchive;
    $zip->open($tmpPath, ZipArchive::CREATE);
    $zip->addFromString('db-dumps'.chr(92).'dump.sql', '-- SQL');
    $zip->close();

    Storage::disk('local')->put(
        $pendingRestore->getPathToLocalCompressedBackup(),
        file_get_contents($tmpPath)
    );
    unlink($tmpPath);

    app(DecompressBackupAction::class)->execute($pendingRestore);

    $dumps = $pendingRestore->getAvailableDbDumps();

    expect($dumps)->toHaveCount(1)
        ->and($dumps->values()->first())
        ->toBe("{$pendingRestore->getPathToLocalDecompressedBackup()}/db-dumps/dump.sql");
});

it('refuses an unencrypted archive when a password was supplied', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/not-really-encrypted.zip',
        connection: 'mysql',
        backupPassword: 'password',
    );

    putCraftedArchive($pendingRestore, function (ZipArchive $zip) {
        $zip->addFromString('db-dumps/dump.sql', '-- SQL');
    });

    app(DecompressBackupAction::class)->execute($pendingRestore);
})
    ->throws(DecompressionFailed::class)
    ->expectExceptionMessage('is not encrypted with AES');

it('extracts files that only the owner can read', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/2023-01-28-mysql-no-compression-encrypted.zip',
        connection: 'mysql',
        backupPassword: 'password',
    );

    app(DownloadBackupAction::class)->execute($pendingRestore);
    app(DecompressBackupAction::class)->execute($pendingRestore);

    $dump = $pendingRestore->getAvailableDbDumps()->values()->first();
    $absolutePathToDump = Storage::disk('local')->path($dump);

    expect(substr(sprintf('%o', fileperms($absolutePathToDump)), -4))->toBe('0600')
        ->and(substr(sprintf('%o', fileperms(dirname($absolutePathToDump))), -4))->toBe('0700');
})->skipOnWindows();

it('stops extracting when the abort is requested', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'local',
        backup: 'backup.zip',
        connection: 'sqlite',
    );

    putCraftedArchive($pendingRestore, function (ZipArchive $zip) {
        $zip->addFromString('db-dumps/first.sql', 'SELECT 1;');
        $zip->addFromString('db-dumps/second.sql', 'SELECT 2;');
    });

    $abort = new RestoreAbort;
    $abort->requestAbort(2);

    expect(fn () => (new DecompressBackupAction)->execute($pendingRestore, $abort))
        ->toThrow(RestoreWasAborted::class);
});

it('extracts normally when no abort was requested', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'local',
        backup: 'backup.zip',
        connection: 'sqlite',
    );

    putCraftedArchive($pendingRestore, function (ZipArchive $zip) {
        $zip->addFromString('db-dumps/first.sql', 'SELECT 1;');
    });

    (new DecompressBackupAction)->execute($pendingRestore, new RestoreAbort);

    expect($pendingRestore->getAvailableDbDumps())->toHaveCount(1);
});
