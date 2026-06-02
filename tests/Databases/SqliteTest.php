<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Wnx\LaravelBackupRestore\Databases\Sqlite;
use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

it('imports sqlite dump', function (string $dumpFile) {
    Event::fake();

    app(Sqlite::class)->importToDatabase($dumpFile, 'sqlite');

    Event::assertDispatched(function (DatabaseDumpImportWasSuccessful $event) use ($dumpFile) {
        return $event->absolutePathToDump === $dumpFile;
    });

    $result = DB::connection('sqlite')->table('users')->count();
    expect($result)->toBe(10);
})->with([
    __DIR__.'/../storage/Laravel/2023-02-28-sqlite-no-compression-no-encryption.sql',
    __DIR__.'/../storage/Laravel/2023-02-28-sqlite-compression-no-encryption.sql.gz',
    __DIR__.'/../storage/Laravel/2023-02-28-sqlite-compression-no-encryption.sql.bz2',
]);

it('throws import failed exception if sqlite dump could not be imported')
    ->tap(fn () => app(Sqlite::class)->importToDatabase('file-does-not-exist', 'sqlite'))
    ->throws(ImportFailed::class);

it('shell-escapes the dump path in the uncompressed sqlite import command', function () {
    $maliciousPath = '/tmp/backup.sql;touch /tmp/lbr_security_test;#.sql';

    $command = app(Sqlite::class)->getImportCommand($maliciousPath, 'sqlite');

    expect($command)->toContain(escapeshellarg($maliciousPath));
});

it('shell-escapes the dump path in the compressed gz sqlite import command', function () {
    $maliciousPath = '/tmp/backup.sql;touch /tmp/lbr_security_test;#.sql.gz';

    $command = app(Sqlite::class)->getImportCommand($maliciousPath, 'sqlite');

    expect($command)->toContain(escapeshellarg($maliciousPath));
});

it('shell-escapes the dump path in the compressed bz2 sqlite import command', function () {
    $maliciousPath = '/tmp/backup.sql;touch /tmp/lbr_security_test;#.sql.bz2';

    $command = app(Sqlite::class)->getImportCommand($maliciousPath, 'sqlite');

    expect($command)->toContain(escapeshellarg($maliciousPath));
});
