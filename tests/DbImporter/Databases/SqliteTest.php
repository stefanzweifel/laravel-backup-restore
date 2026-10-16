<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotStartImport;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\ImportFailed;

it('runs no external command', function () {
    expect(sqliteImporter()->getImportCommand())->toBe([]);
});

it('imports a sqlite dump', function (string $fixture) {
    sqliteImporter()->importFromFile(lbrFixture($fixture));

    expect(DB::connection('sqlite')->table('users')->count())->toBe(10);
})->with([
    '2023-02-28-sqlite-no-compression-no-encryption.sql',
    '2023-02-28-sqlite-compression-no-encryption.sql.gz',
    '2023-02-28-sqlite-compression-no-encryption.sql.bz2',
]);

it('fails when a statement in the dump fails', function () {
    $dump = tempnam(sys_get_temp_dir(), 'lbr-broken-').'.sql';
    file_put_contents($dump, "CREATE TABLE lbr_probe (id int);\nINSERT INTO table_that_does_not_exist VALUES (1);\n");

    try {
        expect(fn () => sqliteImporter()->importFromFile($dump))
            ->toThrow(ImportFailed::class, 'no such table');
    } finally {
        unlink($dump);
    }
});

it('throws when the dump file does not exist', function () {
    sqliteImporter()->importFromFile('file-does-not-exist');
})->throws(CannotStartImport::class);

it('does not run a dot-command found in the dump', function (string $command) {
    // The sqlite3 binary runs .shell and .system lines read from stdin. PDO
    // has no dot-commands, so the line is a syntax error instead.
    $marker = sys_get_temp_dir().'/lbr-pwned-sqlite-'.uniqid();
    $dump = tempnam(sys_get_temp_dir(), 'lbr-payload-').'.sql';
    file_put_contents($dump, $command.' touch '.$marker."\nCREATE TABLE lbr_probe (id int);\n");

    try {
        expect(fn () => sqliteImporter()->importFromFile($dump))->toThrow(ImportFailed::class);
        expect(file_exists($marker))->toBeFalse();
    } finally {
        unlink($dump);

        if (file_exists($marker)) {
            unlink($marker);
        }
    }
})->with(['.shell', '.system']);

it('keeps peak memory flat while reading the dump', function () {
    // The dump is read into memory in one piece, so this records what that
    // costs rather than asserting the memory is not used.
    $fixture = lbrFixture('2023-02-28-sqlite-no-compression-no-encryption.sql');

    $before = memory_get_peak_usage(true);
    sqliteImporter()->importFromFile($fixture);
    $growth = memory_get_peak_usage(true) - $before;

    expect($growth)->toBeLessThan(2 * 1024 * 1024);
    expect(DB::connection('sqlite')->table('users')->count())->toBe(10);
});
