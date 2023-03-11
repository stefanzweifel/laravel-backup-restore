<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Wnx\LaravelBackupRestore\Databases\PostgreSql;
use Wnx\LaravelBackupRestore\Events\DatabaseDumpImportWasSuccessful;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

it('imports pgsql dump', function (string $dumpFile) {
    Event::fake();

    app(PostgreSql::class)->importToDatabase($dumpFile);

    Event::assertDispatched(function (DatabaseDumpImportWasSuccessful $event) use ($dumpFile) {
        return $event->absolutePathToDump === $dumpFile;
    });

    $result = DB::connection('pgsql')->table('users')->count();
    expect($result)->toBe(10);
})->with([
    __DIR__.'/../storage/Laravel/2023-03-04-pgsql-no-compression-no-encryption.sql',
    __DIR__.'/../storage/Laravel/2023-03-04-pgsql-compression-no-encryption.sql.gz',
]);

it('throws import failed exception if pgsql dump could not be imported')
    ->tap(fn () => app(PostgreSql::class)->importToDatabase('file-does-not-exist'))
    ->throws(ImportFailed::class);
