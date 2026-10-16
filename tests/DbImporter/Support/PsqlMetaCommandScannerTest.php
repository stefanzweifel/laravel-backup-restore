<?php

declare(strict_types=1);

use Wnx\LaravelBackupRestore\DbImporter\Exceptions\DumpContainsMetaCommand;
use Wnx\LaravelBackupRestore\DbImporter\Support\PsqlMetaCommandScanner;

function scan(string $dump): string
{
    $stream = fopen('php://memory', 'r+b');
    fwrite($stream, $dump);
    rewind($stream);

    return implode('', iterator_to_array((new PsqlMetaCommandScanner)->pipe($stream), false));
}

it('passes a real pg_dump through unchanged', function (string $fixture) {
    $dump = file_get_contents(lbrFixture($fixture));

    expect(scan($dump))->toBe($dump);
})->with([
    '2023-03-04-pgsql-no-compression-no-encryption.sql',
    '2023-01-28-mysql-no-compression-no-encryption.sql',
]);

it('allows the meta-commands pg_dump emits', function (string $line) {
    expect(scan($line."\n"))->toBe($line."\n");
})->with([
    '\.',
    '\connect laravel_backup_restore',
    '\connect -reuse-previous=on "dbname=\'app\'"',
    '\restrict DswmQJ1bcOabBchaoNgtWZ1OBO4taGlcDTHRmUnccpDcY3Pcha4PHHiwgLxIrdg',
    '\unrestrict DswmQJ1bcOabBchaoNgtWZ1OBO4taGlcDTHRmUnccpDcY3Pcha4PHHiwgLxIrdg',
]);

it('refuses a meta-command that pg_dump does not emit', function (string $line, string $expected) {
    expect(fn () => scan("SET client_encoding = 'UTF8';\n".$line."\nSELECT 1;\n"))
        ->toThrow(DumpContainsMetaCommand::class, $expected);
})->with([
    ['\! touch /tmp/pwned', '\!'],
    ['   \! touch /tmp/pwned', '\!'],
    ['\!touch /tmp/pwned', '\!touch'],
    ['\i /etc/passwd', '\i'],
    ['\copy users from program \'touch /tmp/pwned\'', '\copy'],
    ['\o |touch /tmp/pwned', '\o'],
    ['\g |touch /tmp/pwned', '\g'],
]);

it('refuses a meta-command that is not at the start of a line', function (string $dump, string $expected) {
    // psql starts a meta-command at an unquoted backslash anywhere, so a
    // line-anchored check misses all of these.
    expect(fn () => scan($dump))->toThrow(DumpContainsMetaCommand::class, $expected);
})->with([
    'after a semicolon' => ["SELECT 1; \\! touch /tmp/pwned_midline\n", '\!'],
    'after a tab' => ["CREATE TABLE a(i int);\t\\!touch /tmp/pwned2\n", '\!touch'],
    'after a block comment' => ["SELECT 1 /* c */ \\! touch /tmp/pwned_block\n", '\!'],
    'mid-statement, no terminator' => ["SELECT 1\n\\! touch /tmp/pwned_continuation\n;\n", '\!'],
    'after a closed string' => ["SELECT 'x' \\! touch /tmp/pwned_after_string\n", '\!'],
]);

it('leaves a backslash that psql would not run as a command', function (string $dump) {
    expect(scan($dump))->toBe($dump);
})->with([
    'inside a line comment' => ["SELECT 1; -- \\! touch /tmp/pwned_comment\n"],
    'inside a string literal' => ["SELECT '\\! touch /tmp/pwned_string';\n"],
    'a \\N null in COPY data' => ["COPY t (a, b) FROM stdin;\n1\t\\N\n2\t\\! touch /tmp/pwned_copy\n\\.\n"],
]);

it('names the line the meta-command is on', function () {
    try {
        scan("SELECT 1;\nSELECT 2;\n\\! touch /tmp/pwned\n");
    } catch (DumpContainsMetaCommand $exception) {
        expect($exception->lineNumber)->toBe(3);
        expect($exception->metaCommand)->toBe('\!');
        expect($exception->getMessage())->toContain('line 3');

        return;
    }

    $this->fail('No exception was thrown.');
});

it('treats COPY data as data, backslashes included', function () {
    $dump = <<<'SQL'
    COPY public.users (id, note) FROM stdin;
    1	\! touch /tmp/pwned
    2	\.not the terminator
    \.
    SELECT 1;

    SQL;

    expect(scan($dump))->toBe($dump);
});

it('refuses a meta-command that follows COPY data', function () {
    $dump = "COPY public.users (id) FROM stdin;\n1\n\\.\n\\! touch /tmp/pwned\n";

    expect(fn () => scan($dump))->toThrow(DumpContainsMetaCommand::class);
});

it('does not read a backslash inside a string literal as a command', function () {
    $dump = "INSERT INTO t VALUES ('line one\n\\! not a command\n');\nSELECT 1;\n";

    expect(scan($dump))->toBe($dump);
});

it('does not read a backslash inside a dollar-quoted body as a command', function () {
    $dump = "CREATE FUNCTION f() RETURNS void AS \$\$\n\\! not a command\n\$\$ LANGUAGE sql;\nSELECT 1;\n";

    expect(scan($dump))->toBe($dump);
});

it('does not read a backslash inside a block comment as a command', function () {
    $dump = "/* comment\n\\! not a command\n*/\nSELECT 1;\n";

    expect(scan($dump))->toBe($dump);
});

it('still refuses a meta-command after a string literal closes', function () {
    $dump = "INSERT INTO t VALUES ('a''b');\n\\! touch /tmp/pwned\n";

    expect(fn () => scan($dump))->toThrow(DumpContainsMetaCommand::class);
});
