<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\Actions\ImportDumpAction;
use Wnx\LaravelBackupRestore\Actions\ResetDatabaseAction;
use Wnx\LaravelBackupRestore\PendingRestore;

it('resets database', function ($connection, $backup, $exceptionMessage) {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: $backup,
        connection: $connection,
        backupPassword: null,
    );

    // Download, Decompress and Import Database Backup
    app(DownloadBackupAction::class)->execute($pendingRestore);
    app(DecompressBackupAction::class)->execute($pendingRestore);
    app(ImportDumpAction::class)->execute($pendingRestore);

    // Assert database is filled
    $result = DB::connection($connection)->table('users')->count();
    expect($result)->toBe(10);

    // Run Reset Action
    app(ResetDatabaseAction::class)->execute($pendingRestore);

    // Assert database is empty
    try {
        $result = DB::connection($connection)->table('users')->count();
        expect($result)->not()->toBe(10);
    } catch (QueryException $exception) {
        expect($exception->getMessage())
            ->toContain($exceptionMessage);
    }
})->with([
    [
        'connection' => 'mysql',
        'backup' => 'Laravel/2023-01-28-mysql-no-compression-no-encryption.zip',
        'exceptionMessage' => 'Base table or view not found',
    ],
    [
        'connection' => 'sqlite',
        'backup' => 'Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip',
        'exceptionMessage' => 'no such table',
    ],

    [
        'connection' => 'pgsql',
        'backup' => 'Laravel/2023-03-04-pgsql-no-compression-no-encryption.zip',
        'exceptionMessage' => 'Undefined table: 7 ERROR:  relation "users" does not exist',
    ],
]);
