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

it('names the statement that failed', function () {
    $dump = tempnam(sys_get_temp_dir(), 'lbr-broken-').'.sql';
    file_put_contents($dump, "CREATE TABLE lbr_probe (id int);\nINSERT INTO table_that_does_not_exist VALUES (1);\n");

    try {
        expect(fn () => sqliteImporter()->importFromFile($dump))
            ->toThrow(function (ImportFailed $exception) {
                expect($exception->getMessage())
                    ->toContain('statement 2')
                    ->toContain('INSERT INTO table_that_does_not_exist')
                    ->toContain('no such table');
            });
    } finally {
        unlink($dump);
    }
});

it('shortens a long statement in the error message', function () {
    $dump = tempnam(sys_get_temp_dir(), 'lbr-broken-').'.sql';
    file_put_contents($dump, "INSERT INTO nope VALUES ('".str_repeat('x', 5000)."');\n");

    try {
        expect(fn () => sqliteImporter()->importFromFile($dump))
            ->toThrow(function (ImportFailed $exception) {
                expect(strlen($exception->getMessage()))->toBeLessThan(1024);
                expect($exception->getMessage())->toContain('(truncated)');
            });
    } finally {
        unlink($dump);
    }
});

it('does not check the abort token when none was set', function () {
    sqliteImporter()->importFromFile(lbrFixture('2023-02-28-sqlite-no-compression-no-encryption.sql'));

    expect(DB::connection('sqlite')->table('users')->count())->toBe(10);
});
