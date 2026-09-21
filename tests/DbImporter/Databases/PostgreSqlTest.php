<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\DbImporter\Databases\PostgreSql;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotStartImport;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\DumpContainsMetaCommand;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\ImportFailed;

function pgsqlDumpWith(string $contents): string
{
    $dump = tempnam(sys_get_temp_dir(), 'lbr-pgsql-').'.sql';
    file_put_contents($dump, $contents);

    return $dump;
}

it('builds the psql command as an array', function () {
    $command = PostgreSql::create()
        ->setDbName('laravel')
        ->setUserName('root')
        ->setHost('127.0.0.1')
        ->setPort(5432)
        ->getImportCommand();

    expect($command)->toBe([
        'psql',
        '--no-password',
        '--set',
        'ON_ERROR_STOP=1',
        '--host=127.0.0.1',
        '--port=5432',
        '--username=root',
        '--dbname=laravel',
    ]);
})->group('pgsql');

it('keeps the password out of the command and passes it in the environment', function () {
    $importer = PostgreSql::create()->setDbName('laravel')->setPassword('secret')->setSearchPath('public');

    expect($importer->getImportCommand())->not->toContain('secret');
    expect($importer->getEnvironmentVariables())->toBe([
        'PGPASSWORD' => 'secret',
        'PGOPTIONS' => '--search_path=public',
    ]);
})->group('pgsql');

it('escapes whitespace in the search path, which libpq splits on', function () {
    $environment = PostgreSql::create()->setDbName('laravel')->setSearchPath('"$user", public')->getEnvironmentVariables();

    expect($environment['PGOPTIONS'])->toBe('--search_path="$user",\ public');
})->group('pgsql');

it('imports a plain pgsql dump', function (string $fixture) {
    pgsqlImporter()->importFromFile(lbrFixture($fixture));

    expect(DB::connection('pgsql')->table('users')->count())->toBe(10);
})->with([
    '2023-03-04-pgsql-no-compression-no-encryption.sql',
    '2023-03-04-pgsql-compression-no-encryption.sql.gz',
    '2023-03-04-pgsql-compression-no-encryption.sql.bz2',
])->group('pgsql');

it('recognises a custom-format dump by its magic number and uses pg_restore', function () {
    $archive = Storage::disk('local')->path('lbr-pgdmp');
    @mkdir(dirname($archive), 0755, true);

    $zip = new ZipArchive;
    $zip->open(lbrFixture('2025-12-26-pgsql-no-compression-custom-extension-binary-dump.zip'));
    $zip->extractTo(dirname($archive));
    $zip->close();

    $dump = dirname($archive).'/db-dumps/postgresql-laravel_backup_restore_demo_app.backup';

    $importer = pgsqlImporter();
    expect($importer->dumpIsCustomFormat($dump))->toBeTrue();

    $importer->importFromFile($dump);

    expect(DB::connection('pgsql')->table('users')->count())->toBeGreaterThan(0);
})->group('pgsql');

it('fails when a statement in the dump fails', function () {
    // Without --set ON_ERROR_STOP=1 psql exits 0 here and the restore is
    // reported as successful.
    $dump = pgsqlDumpWith("CREATE TABLE lbr_probe (id int);\nSELECT * FROM table_that_does_not_exist;\n");

    try {
        expect(fn () => pgsqlImporter()->importFromFile($dump))->toThrow(ImportFailed::class);
    } finally {
        unlink($dump);
    }
})->group('pgsql');

it('refuses a dump carrying a psql meta-command and runs nothing', function () {
    $marker = sys_get_temp_dir().'/lbr-pwned-psql-'.uniqid();
    $dump = pgsqlDumpWith("SELECT 1;\n\\! touch ".$marker."\nSELECT 2;\n");

    try {
        expect(fn () => pgsqlImporter()->importFromFile($dump))
            ->toThrow(DumpContainsMetaCommand::class, '\!');

        expect(file_exists($marker))->toBeFalse();
    } finally {
        unlink($dump);

        if (file_exists($marker)) {
            unlink($marker);
        }
    }
})->group('pgsql');

it('hands meta-commands to psql when the escape hatch is on', function () {
    // Proves the hole the scanner closes is real, and that the opt-out works.
    $marker = sys_get_temp_dir().'/lbr-allowed-psql-'.uniqid();
    $dump = pgsqlDumpWith('\\! touch '.$marker."\nSELECT 1;\n");

    try {
        pgsqlImporter()->allowMetaCommands()->importFromFile($dump);

        expect(file_exists($marker))->toBeTrue();
    } finally {
        unlink($dump);

        if (file_exists($marker)) {
            unlink($marker);
        }
    }
})->group('pgsql');

it('throws when the dump file does not exist', function () {
    pgsqlImporter()->importFromFile('file-does-not-exist');
})->throws(CannotStartImport::class)->group('pgsql');
