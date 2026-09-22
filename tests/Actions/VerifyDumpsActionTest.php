<?php

declare(strict_types=1);

use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\Actions\VerifyDumpsAction;
use Wnx\LaravelBackupRestore\Exceptions\DumpIsNotRestorable;
use Wnx\LaravelBackupRestore\Exceptions\NoDatabaseDumpsFound;
use Wnx\LaravelBackupRestore\PendingRestore;

function decompressedRestore(string $backup): PendingRestore
{
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: $backup,
        connection: 'sqlite-restore',
        backupPassword: null,
    );

    app(DownloadBackupAction::class)->execute($pendingRestore);
    app(DecompressBackupAction::class)->execute($pendingRestore);

    return $pendingRestore;
}

it('returns the dumps found in the backup', function (string $backup) {
    $dumps = app(VerifyDumpsAction::class)->execute(decompressedRestore($backup), verifyContent: true);

    expect($dumps)->toHaveCount(1);
})->with([
    'plain sql' => 'Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip',
    'gzipped sql' => 'Laravel/2023-01-28-mysql-compression-no-encryption.zip',
]);

it('accepts a binary dump it cannot read as text', function () {
    config(['backup.backup.database_dump_file_extension' => 'backup']);

    $dumps = app(VerifyDumpsAction::class)->execute(
        decompressedRestore('Laravel/2025-12-26-pgsql-no-compression-custom-extension-binary-dump.zip'),
        verifyContent: true
    );

    expect($dumps)->toHaveCount(1);
});

it('throws if the backup holds no dumps', function () {
    app(VerifyDumpsAction::class)->execute(decompressedRestore('Laravel/2023-03-11-no-dumps.zip'));
})->throws(NoDatabaseDumpsFound::class);

it('throws if a dump is empty and the content is verified', function () {
    app(VerifyDumpsAction::class)->execute(
        decompressedRestore('Laravel/2023-01-28-mysql-no-compression-no-encryption-empty-dump.zip'),
        verifyContent: true
    );
})->throws(DumpIsNotRestorable::class, 'The database dump "empty.sql" is empty.');

it('does not look at the content unless asked to', function () {
    $dumps = app(VerifyDumpsAction::class)->execute(
        decompressedRestore('Laravel/2023-01-28-mysql-no-compression-no-encryption-empty-dump.zip')
    );

    expect($dumps)->toHaveCount(1);
});
