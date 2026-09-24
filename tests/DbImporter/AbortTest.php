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

it('stops a sqlite import between statements', function () {
    $abort = new RestoreAbort;

    // A dump big enough that the alarm lands somewhere in the middle of the
    // statement loop rather than before or after it.
    $dump = tempnam(sys_get_temp_dir(), 'lbr-abort-').'.sql';
    $handle = fopen($dump, 'wb');
    fwrite($handle, "CREATE TABLE lbr_probe (id integer, payload text);\n");
    for ($i = 0; $i < 40_000; $i++) {
        fwrite($handle, "INSERT INTO lbr_probe VALUES ({$i}, 'padding-padding-padding-padding');\n");
    }
    fclose($handle);

    // A real signal, delivered while the import is running. pcntl_async_signals()
    // is what lets the handler run between two PDO::exec() calls.
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
            ->toBeLessThan(40_000);
    } finally {
        pcntl_alarm(0);
        pcntl_signal(SIGALRM, SIG_DFL);
        unlink($dump);
    }
})->skip(fn () => ! extension_loaded('pcntl'), 'Requires ext-pcntl.');

it('imports normally when no abort was requested', function () {
    sqliteImporter()->abortWith(new RestoreAbort)
        ->importFromFile(lbrFixture('2023-02-28-sqlite-no-compression-no-encryption.sql'));

    expect(DB::connection('sqlite')->table('users')->count())->toBe(10);
});
