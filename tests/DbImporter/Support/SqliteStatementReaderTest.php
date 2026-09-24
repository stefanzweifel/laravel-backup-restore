<?php

declare(strict_types=1);

use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotSetParameter;
use Wnx\LaravelBackupRestore\DbImporter\Support\SqliteStatementReader;

/**
 * @return array<int, string>
 */
function readStatements(string $dump, int $chunkSize = 65536): array
{
    $stream = fopen('php://memory', 'r+b');
    fwrite($stream, $dump);
    rewind($stream);

    return iterator_to_array((new SqliteStatementReader($chunkSize))->pipe($stream), false);
}

it('splits a dump into one statement per semicolon', function () {
    expect(readStatements("CREATE TABLE a (id int);\nINSERT INTO a VALUES (1);\n"))
        ->toBe(['CREATE TABLE a (id int);', 'INSERT INTO a VALUES (1);']);
});

it('ignores a semicolon that is not a statement end', function (string $statement) {
    expect(readStatements($statement."\nSELECT 2;\n"))->toBe([$statement, 'SELECT 2;']);
})->with([
    'a semicolon in a string' => "INSERT INTO a VALUES ('one; two');",
    'a doubled quote in a string' => "INSERT INTO a VALUES ('it''s; fine');",
    'a semicolon in a quoted identifier' => 'CREATE TABLE "od; d" (id int);',
    'a semicolon in a bracketed identifier' => 'CREATE TABLE [od; d] (id int);',
    'a semicolon in a backquoted identifier' => 'CREATE TABLE `od; d` (id int);',
    'a semicolon in a line comment' => "SELECT 1 -- and; more\n;",
    'a semicolon in a block comment' => 'SELECT /* one; two */ 1;',
]);

it('keeps a trigger body in one statement', function () {
    $trigger = <<<'SQL'
CREATE TRIGGER touch_updated AFTER UPDATE ON users
BEGIN
  UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
  UPDATE audit SET seen = 1;
END;
SQL;

    expect(readStatements($trigger."\nSELECT 2;\n"))->toBe([$trigger, 'SELECT 2;']);
});

it('keeps a temporary trigger body in one statement', function () {
    $trigger = 'CREATE TEMP TRIGGER t AFTER INSERT ON a BEGIN DELETE FROM b; END;';

    expect(readStatements($trigger."\nSELECT 2;\n"))->toBe([$trigger, 'SELECT 2;']);
});

it('does not treat a transaction BEGIN as a trigger body', function () {
    expect(readStatements("BEGIN TRANSACTION;\nINSERT INTO a VALUES (1);\nCOMMIT;\n"))
        ->toBe(['BEGIN TRANSACTION;', 'INSERT INTO a VALUES (1);', 'COMMIT;']);
});

it('does not treat a column named begin as a trigger body', function () {
    expect(readStatements('CREATE TABLE a ("begin" int, "end" int);'."\nSELECT 2;\n"))
        ->toBe(['CREATE TABLE a ("begin" int, "end" int);', 'SELECT 2;']);
});

it('reads the same statements no matter where the chunks fall', function (int $chunkSize) {
    $dump = <<<'SQL'
PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE "od;d" ("begin" int, payload text);
INSERT INTO "od;d" VALUES (1, 'it''s; a -- value /* not */ a comment');
/* a block
   comment; spanning lines */
CREATE TRIGGER t AFTER UPDATE ON "od;d"
BEGIN
  UPDATE audit SET n = CASE WHEN 1 THEN 2 ELSE 3 END;
END;
COMMIT;
SQL;

    expect(readStatements($dump, $chunkSize))->toBe(readStatements($dump));
})->with([1, 2, 3, 7, 16, 64]);

it('does not yield a statement that is only whitespace and semicolons', function () {
    expect(readStatements(";\n;;\nSELECT 1;\n\n;"))->toBe(['SELECT 1;']);
});

it('yields a trailing statement that has no semicolon', function () {
    expect(readStatements("SELECT 1;\nSELECT 2"))->toBe(['SELECT 1;', 'SELECT 2']);
});

it('splits a real sqlite dump into executable statements', function () {
    $statements = readStatements(file_get_contents(lbrFixture('2023-02-28-sqlite-no-compression-no-encryption.sql')));

    expect($statements)->toHaveCount(29)
        ->and($statements[0])->toBe('PRAGMA foreign_keys=OFF;')
        ->and($statements[1])->toBe('BEGIN TRANSACTION;')
        ->and(end($statements))->toBe('COMMIT;');
});

it('refuses a chunk size below one byte', function (int $chunkSize) {
    expect(fn () => new SqliteStatementReader($chunkSize))
        ->toThrow(CannotSetParameter::class, '`chunkSize` must be at least 1, got '.$chunkSize);
})->with([0, -1]);
