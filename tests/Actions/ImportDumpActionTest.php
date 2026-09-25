<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\Actions\ImportDumpAction;
use Wnx\LaravelBackupRestore\Events\DatabaseRestored;
use Wnx\LaravelBackupRestore\Exceptions\NoDatabaseDumpsFound;
use Wnx\LaravelBackupRestore\Exceptions\RestoreWasAborted;
use Wnx\LaravelBackupRestore\PendingRestore;
use Wnx\LaravelBackupRestore\RestoreAbort;

it('imports mysql dump', function () {
    Event::fake();

    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/2023-01-28-mysql-no-compression-no-encryption.zip',
        connection: 'mysql-restore',
        backupPassword: null,
    );

    app(DownloadBackupAction::class)->execute($pendingRestore);
    app(DecompressBackupAction::class)->execute($pendingRestore);
    app(ImportDumpAction::class)->execute($pendingRestore);

    Event::assertDispatched(function (DatabaseRestored $event) use ($pendingRestore) {
        return $event->pendingRestore->connection === $pendingRestore->connection;
    });
    Event::assertDispatchedTimes(DatabaseRestored::class, 1);
});

it('throws no database dumps found exception if backup does not contain any database dumps', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/2023-03-11-no-dumps.zip',
        connection: 'mysql-restore',
        backupPassword: null,
    );

    app(DownloadBackupAction::class)->execute($pendingRestore);
    app(DecompressBackupAction::class)->execute($pendingRestore);

    try {
        app(ImportDumpAction::class)->execute($pendingRestore);
        $this->fail('Expected NoDatabaseDumpsFound to be thrown.');
    } catch (NoDatabaseDumpsFound $exception) {
        expect($exception->getMessage())->toBe('The backup "Laravel/2023-03-11-no-dumps.zip" contains no database dumps.')
            ->and($exception->hint())->toContain('not-a-sql-file.txt');
    }
});

it('aborts before importing the first dump when the abort was already requested', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip',
        connection: 'sqlite-restore',
        backupPassword: null,
    );

    app(DownloadBackupAction::class)->execute($pendingRestore);
    app(DecompressBackupAction::class)->execute($pendingRestore);

    $abort = new RestoreAbort;
    $abort->requestAbort(2);

    expect(fn () => app(ImportDumpAction::class)->execute($pendingRestore, $abort))
        ->toThrow(RestoreWasAborted::class);

    expect(Schema::connection('sqlite')->hasTable('users'))->toBeFalse();
});

it('imports normally when no abort was requested', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/2023-02-28-sqlite-no-compression-no-encryption.zip',
        connection: 'sqlite-restore',
        backupPassword: null,
    );

    app(DownloadBackupAction::class)->execute($pendingRestore);
    app(DecompressBackupAction::class)->execute($pendingRestore);
    app(ImportDumpAction::class)->execute($pendingRestore, new RestoreAbort);

    expect(DB::connection('sqlite')->table('users')->count())->toBe(10);
});
