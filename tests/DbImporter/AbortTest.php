<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\ImportAborted;
use Wnx\LaravelBackupRestore\RestoreAbort;

it('returns the importer for chaining', function () {
    $importer = sqliteImporter();

    expect($importer->abortWith(new RestoreAbort))->toBe($importer);
});

it('refuses to start an import when the abort was already requested', function () {
    $abort = new RestoreAbort;
    $abort->requestAbort(15);

    expect(fn () => sqliteImporter()->abortWith($abort)
        ->importFromFile(lbrFixture('2023-02-28-sqlite-no-compression-no-encryption.sql')))
        ->toThrow(ImportAborted::class);

    expect(Schema::connection('sqlite')->hasTable('users'))->toBeFalse();
});

it('refuses to start a child process when the abort was already requested', function () {
    $abort = new RestoreAbort;
    $abort->requestAbort(15);

    // A CLI importer, so the guard under test is the one in
    // DbImporter::runImport() rather than the poll in the sqlite loop.
    expect(fn () => mysqlImporter()->abortWith($abort)
        ->importFromFile(lbrFixture('2023-01-28-mysql-no-compression-no-encryption.sql')))
        ->toThrow(ImportAborted::class);

    // Nothing was imported, which is only true if no mysql child ever ran.
    expect(Schema::connection('mysql-restore')->hasTable('users'))->toBeFalse();
});

it('stops a sqlite import between statements', function () {
    $abort = new RestoreAbort;

    // A dump big enough that the statement loop comfortably outruns the alarm,
    // which has one-second granularity. Sized so the import takes several times
    // that on a fast machine and the alarm lands in the middle of the loop.
    $dump = tempnam(sys_get_temp_dir(), 'lbr-abort-').'.sql';
    $handle = fopen($dump, 'wb');
    fwrite($handle, "CREATE TABLE lbr_probe (id integer, payload text);\n");
    for ($i = 0; $i < 150_000; $i++) {
        fwrite($handle, "INSERT INTO lbr_probe VALUES ({$i}, 'padding-padding-padding-padding');\n");
    }
    fclose($handle);

    // A real signal, delivered while the import is running. pcntl_async_signals()
    // is what lets the handler run between two PDO::exec() calls.
    $asyncSignalsWereOn = pcntl_async_signals();
    pcntl_async_signals(true);
    pcntl_signal(SIGALRM, function () use ($abort): void {
        $abort->requestAbort(SIGTERM);
    });
    pcntl_alarm(1);

    try {
        expect(fn () => sqliteImporter()->abortWith($abort)->importFromFile($dump))
            ->toThrow(ImportAborted::class);

        // Some rows made it in before the alarm; the point is that not all did.
        expect(DB::connection('sqlite')->table('lbr_probe')->count())
            ->toBeGreaterThan(0)
            ->toBeLessThan(150_000);
    } finally {
        pcntl_alarm(0);
        pcntl_signal(SIGALRM, SIG_DFL);
        pcntl_async_signals($asyncSignalsWereOn);
        unlink($dump);
    }
})->skip(fn () => ! extension_loaded('pcntl'), 'Requires ext-pcntl.');

it('imports normally when no abort was requested', function () {
    sqliteImporter()->abortWith(new RestoreAbort)
        ->importFromFile(lbrFixture('2023-02-28-sqlite-no-compression-no-encryption.sql'));

    expect(DB::connection('sqlite')->table('users')->count())->toBe(10);
});

it('reports a killed CLI import as aborted', function () {
    $abort = new RestoreAbort;

    // SELECT SLEEP() keeps the mysql client busy long enough for the alarm to
    // land while the child is still running, so the stopper kills a live
    // process instead of racing a finished one.
    $dump = tempnam(sys_get_temp_dir(), 'lbr-abort-').'.sql';
    file_put_contents($dump, "SELECT SLEEP(5);\nSELECT SLEEP(5);\n");

    $asyncSignalsWereOn = pcntl_async_signals();
    pcntl_async_signals(true);
    pcntl_signal(SIGALRM, function () use ($abort): void {
        $abort->requestAbort(SIGTERM);
    });
    pcntl_alarm(1);

    try {
        // The child dies of the signal the stopper sent it, which Symfony
        // reports as a RuntimeException out of wait(). That must not be
        // mistaken for a binary that could not be started.
        expect(fn () => mysqlImporter()->abortWith($abort)->importFromFile($dump))
            ->toThrow(ImportAborted::class);
    } finally {
        pcntl_alarm(0);
        pcntl_signal(SIGALRM, SIG_DFL);
        pcntl_async_signals($asyncSignalsWereOn);
        unlink($dump);
    }
})->skip(
    fn () => ! extension_loaded('pcntl') || ! extension_loaded('posix'),
    'Requires ext-pcntl and ext-posix.'
);
