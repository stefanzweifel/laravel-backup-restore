<?php

declare(strict_types=1);

use Wnx\LaravelBackupRestore\DbImporter\Databases\MySql;
use Wnx\LaravelBackupRestore\DbImporter\Databases\PostgreSql;
use Wnx\LaravelBackupRestore\DbImporter\Databases\Sqlite;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\ImportFailed;

const SHELL_PAYLOAD = "'; touch /tmp/pwned; #";

const SHELL_PAYLOAD_SUBSTITUTION = '$(touch /tmp/pwned2)';

function payloadFiles(): array
{
    return ['/tmp/pwned', '/tmp/pwned2'];
}

function removePayloadFiles(): void
{
    foreach (payloadFiles() as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}

/**
 * Asserts the value appears in the argument array as exactly one element,
 * either on its own or as the tail of a --flag=value element.
 */
function expectSingleArgument(array $command, string $value): void
{
    $matches = array_values(array_filter(
        $command,
        fn (string $argument): bool => str_contains($argument, $value)
    ));

    expect($matches)->toHaveCount(1);
    expect($matches[0])->toEndWith($value);
}

beforeEach(fn () => removePayloadFiles());
afterEach(fn () => removePayloadFiles());

it('keeps every hostile value as one argument in the pgsql command', function (string $payload) {
    $importer = PostgreSql::create()
        ->setDbName('db'.$payload)
        ->setUserName('user'.$payload)
        ->setHost('host'.$payload)
        ->setPort(5432)
        ->setImportBinaryPath('/usr/bin/path'.$payload)
        ->addExtraOption('--option='.$payload);

    $command = $importer->getImportCommand();

    expect($command)->toBeArray();
    expectSingleArgument($command, 'db'.$payload);
    expectSingleArgument($command, 'user'.$payload);
    expectSingleArgument($command, 'host'.$payload);
    expectSingleArgument($command, '--option='.$payload);

    // The binary path is the first element and nothing else. The separator is
    // the platform's, because a configured path on Windows uses backslashes.
    expect($command[0])->toBe('/usr/bin/path'.$payload.DIRECTORY_SEPARATOR.'psql');
})->with([SHELL_PAYLOAD, SHELL_PAYLOAD_SUBSTITUTION]);

it('keeps every hostile value as one argument in the mysql command', function (string $payload) {
    $importer = MySql::create()
        ->setDbName('db'.$payload)
        ->setUserName('user'.$payload)
        ->setHost('host'.$payload)
        ->setImportBinaryPath('/usr/bin/path'.$payload)
        ->addExtraOption('--option='.$payload);

    $command = $importer->getImportCommand();

    expect($command)->toBeArray();
    expectSingleArgument($command, 'db'.$payload);
    expectSingleArgument($command, '--option='.$payload);
    expect($command[0])->toBe('/usr/bin/path'.$payload.DIRECTORY_SEPARATOR.'mysql');

    // The user name and host go into the credentials file, not into argv.
    $credentials = $importer->getContentsOfCredentialsFile();
    expect($command)->not->toContain('user'.$payload);
    expect($credentials)->toContain('user'.$payload);
    expect($credentials)->toContain('host'.$payload);

    // Each credential stays on one line of the option file.
    $userLines = array_values(array_filter(
        explode(PHP_EOL, $credentials),
        fn (string $line): bool => str_starts_with($line, 'user = ')
    ));
    expect($userLines)->toHaveCount(1);
    expect($userLines[0])->toBe('user = "user'.$payload.'"');
})->with([SHELL_PAYLOAD, SHELL_PAYLOAD_SUBSTITUTION]);

it('does not run a shell when a pgsql import with hostile values fails', function (string $payload) {
    $importer = PostgreSql::create()
        ->setDbName('db'.$payload)
        ->setUserName('user'.$payload)
        ->setHost('127.0.0.1')
        ->setPort(5432)
        ->setPassword('password'.$payload);

    expect(fn () => $importer->importFromFile(lbrFixture('2023-03-04-pgsql-no-compression-no-encryption.sql')))
        ->toThrow(ImportFailed::class);

    foreach (payloadFiles() as $file) {
        expect(file_exists($file))->toBeFalse();
    }
})->with([SHELL_PAYLOAD, SHELL_PAYLOAD_SUBSTITUTION]);

it('does not run a shell when a mysql import with hostile values fails', function (string $payload) {
    $importer = MySql::create()
        ->setDbName('db'.$payload)
        ->setUserName('user'.$payload)
        ->setHost('127.0.0.1')
        ->setPassword('password'.$payload)
        ->addExtraOption('--option='.$payload);

    expect(fn () => $importer->importFromFile(lbrFixture('2023-01-28-mysql-no-compression-no-encryption.sql')))
        ->toThrow(ImportFailed::class);

    foreach (payloadFiles() as $file) {
        expect(file_exists($file))->toBeFalse();
    }
})->with([SHELL_PAYLOAD, SHELL_PAYLOAD_SUBSTITUTION]);

it('does not run a shell when a sqlite database path carries a payload', function (string $payload) {
    // The payload contains slashes, so the path names directories that do not
    // exist. PDO reports that as an open failure, which is what using the value
    // as a literal path looks like. The wording of that failure differs between
    // platforms, so only the failure itself is asserted.
    $database = sys_get_temp_dir().'/lbr-injection-'.$payload.'.sqlite';

    expect(fn () => Sqlite::create()->setDbName($database)->importFromFile(
        lbrFixture('2023-02-28-sqlite-no-compression-no-encryption.sql')
    ))->toThrow(ImportFailed::class);

    expect(file_exists($database))->toBeFalse();

    foreach (payloadFiles() as $file) {
        expect(file_exists($file))->toBeFalse();
    }
})->with([SHELL_PAYLOAD, SHELL_PAYLOAD_SUBSTITUTION]);

it('imports into a sqlite database whose path carries shell characters', function () {
    // Same characters, minus the slashes, so the file can actually be created.
    $database = sys_get_temp_dir().'/lbr-injection-'.str_replace('/', '_', SHELL_PAYLOAD).'.sqlite';

    $connection = null;

    try {
        Sqlite::create()->setDbName($database)->importFromFile(
            lbrFixture('2023-02-28-sqlite-no-compression-no-encryption.sql')
        );

        expect(file_exists($database))->toBeTrue();

        $connection = new PDO('sqlite:'.$database);
        expect((int) $connection->query('select count(*) from users')->fetchColumn())->toBe(10);
    } finally {
        // Windows refuses to unlink a file that is still open.
        $connection = null;

        if (file_exists($database)) {
            unlink($database);
        }
    }

    foreach (payloadFiles() as $file) {
        expect(file_exists($file))->toBeFalse();
    }
});

it('does not leave the mysql credentials file behind', function () {
    $importer = MySql::create()->setDbName('db-does-not-exist')->setHost('127.0.0.1');

    $before = glob(sys_get_temp_dir().'/lbr-importer-*') ?: [];

    try {
        $importer->importFromFile(lbrFixture('2023-01-28-mysql-no-compression-no-encryption.sql'));
    } catch (ImportFailed) {
        // Expected: the database does not exist.
    }

    expect(glob(sys_get_temp_dir().'/lbr-importer-*') ?: [])->toBe($before);
});
