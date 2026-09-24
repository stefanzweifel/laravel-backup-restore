<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\DbImporter\Databases\MariaDb;
use Wnx\LaravelBackupRestore\DbImporter\Databases\MySql;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotStartImport;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\ImportFailed;

it('builds the import command as an array', function () {
    $command = MySql::create()->setDbName('laravel')->addExtraOption('--skip-ssl')->getImportCommand();

    expect($command)->toBe(['mysql', '--skip-ssl', 'laravel']);
});

it('prefixes the binary with the configured path', function () {
    $command = MySql::create()->setDbName('laravel')->setImportBinaryPath('/usr/bin')->getImportCommand();

    expect($command[0])->toBe('/usr/bin'.DIRECTORY_SEPARATOR.'mysql');
});

it('writes the credentials to an option file instead of the command', function () {
    $importer = MySql::create()
        ->setDbName('laravel')
        ->setUserName('root')
        ->setPassword('secret')
        ->setHost('127.0.0.1')
        ->setPort(3307);

    expect($importer->getContentsOfCredentialsFile())->toBe(
        '[client]'.PHP_EOL.
        'user = "root"'.PHP_EOL.
        'password = "secret"'.PHP_EOL.
        'host = "127.0.0.1"'.PHP_EOL.
        'port = 3307'.PHP_EOL
    );

    expect($importer->getImportCommand())->not->toContain('secret');
});

it('prefers the socket over host and port in the option file', function () {
    $credentials = MySql::create()
        ->setDbName('laravel')
        ->setHost('127.0.0.1')
        ->setSocket('/tmp/mysql.sock')
        ->getContentsOfCredentialsFile();

    expect($credentials)->toContain('socket = "/tmp/mysql.sock"');
    expect($credentials)->not->toContain('host');
});

it('escapes option file values that would otherwise break the file', function () {
    $credentials = MySql::create()->setDbName('laravel')->setPassword("a\"b\\c\nd")->getContentsOfCredentialsFile();

    expect($credentials)->toContain('password = "a\\"b\\\\c\\nd"');
    // [client], password, port: the newline in the password did not add a line.
    expect(substr_count($credentials, PHP_EOL))->toBe(3);
});

it('imports a mysql dump', function (string $fixture) {
    mysqlImporter()->importFromFile(lbrFixture($fixture));

    expect(DB::connection('mysql-restore')->table('users')->count())->toBe(10);
})->with([
    '2023-01-28-mysql-no-compression-no-encryption.sql',
    '2023-01-28-mysql-compression-no-encryption.sql.gz',
    '2023-01-28-mysql-compression-no-encryption.sql.bz2',
]);

it('fails when a statement in the dump fails', function () {
    $dump = tempnam(sys_get_temp_dir(), 'lbr-broken-').'.sql';
    file_put_contents($dump, "CREATE TABLE lbr_probe (id int);\nINSERT INTO table_that_does_not_exist VALUES (1);\n");

    try {
        expect(fn () => mysqlImporter()->importFromFile($dump))->toThrow(ImportFailed::class);
    } finally {
        unlink($dump);
    }
});

it('reports the exit code and stderr on the exception', function () {
    $dump = tempnam(sys_get_temp_dir(), 'lbr-broken-').'.sql';
    file_put_contents($dump, "SELECT * FROM table_that_does_not_exist;\n");

    try {
        mysqlImporter()->importFromFile($dump);
        $this->fail('No exception was thrown.');
    } catch (ImportFailed $exception) {
        expect($exception->exitCode)->toBeGreaterThan(0);
        expect($exception->errorOutput)->toContain('table_that_does_not_exist');
    } finally {
        unlink($dump);
    }
});

it('throws when the dump file does not exist', function () {
    mysqlImporter()->importFromFile('file-does-not-exist');
})->throws(CannotStartImport::class);

it('uses the mariadb binary for mariadb', function () {
    expect(MariaDb::create()->setDbName('laravel')->getImportCommand())->toBe(['mariadb', 'laravel']);
});
