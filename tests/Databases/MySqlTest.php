<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Wnx\LaravelBackupRestore\Databases\MySql;
use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

it('imports mysql dump', function (string $dumpFile) {
    Event::fake();

    app(MySql::class)->importToDatabase($dumpFile, 'mysql');

    Event::assertDispatched(function (DatabaseDumpImportWasSuccessful $event) use ($dumpFile) {
        return $event->absolutePathToDump === $dumpFile;
    });

    $result = DB::connection('mysql')->table('users')->count();
    expect($result)->toBe(10);
})->with([
    __DIR__.'/../storage/Laravel/2023-01-28-mysql-no-compression-no-encryption.sql',
    __DIR__.'/../storage/Laravel/2023-01-28-mysql-compression-no-encryption.sql.gz',
]);

it('throws import failed exception if mysql dump could not be imported')
    ->tap(fn () => app(MySql::class)->importToDatabase('file-does-not-exist', 'mysql'))
    ->throws(ImportFailed::class);
