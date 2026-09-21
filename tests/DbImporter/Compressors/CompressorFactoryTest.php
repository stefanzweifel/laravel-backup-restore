<?php

declare(strict_types=1);

use Wnx\LaravelBackupRestore\DbImporter\Compressors\Bzip2Compressor;
use Wnx\LaravelBackupRestore\DbImporter\Compressors\CompressorFactory;
use Wnx\LaravelBackupRestore\DbImporter\Compressors\GzipCompressor;
use Wnx\LaravelBackupRestore\DbImporter\Compressors\NullCompressor;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\CannotStartImport;

if (! function_exists('lbrFixture')) {
    function lbrFixture(string $name): string
    {
        return __DIR__.'/../../storage/Laravel/'.$name;
    }
}

it('detects the compressor of the committed fixtures', function (string $fixture, string $expected) {
    expect(CompressorFactory::forDumpFile(lbrFixture($fixture)))->toBeInstanceOf($expected);
})->with([
    ['2023-01-28-mysql-no-compression-no-encryption.sql', NullCompressor::class],
    ['2023-01-28-mysql-compression-no-encryption.sql.gz', GzipCompressor::class],
    ['2023-01-28-mysql-compression-no-encryption.sql.bz2', Bzip2Compressor::class],
    ['2023-03-04-pgsql-no-compression-no-encryption.sql', NullCompressor::class],
    ['2023-03-04-pgsql-compression-no-encryption.sql.gz', GzipCompressor::class],
    ['2023-03-04-pgsql-compression-no-encryption.sql.bz2', Bzip2Compressor::class],
    ['2023-02-28-sqlite-no-compression-no-encryption.sql', NullCompressor::class],
    ['2023-02-28-sqlite-compression-no-encryption.sql.gz', GzipCompressor::class],
    ['2023-02-28-sqlite-compression-no-encryption.sql.bz2', Bzip2Compressor::class],
]);

it('prefers the magic bytes over the file extension', function (string $fixture, string $expected) {
    // spatie/laravel-backup can be configured to give the dump any extension.
    $misnamed = sys_get_temp_dir().'/lbr-misnamed-'.uniqid().'.sql';
    copy(lbrFixture($fixture), $misnamed);

    try {
        expect(CompressorFactory::forDumpFile($misnamed))->toBeInstanceOf($expected);
    } finally {
        unlink($misnamed);
    }
})->with([
    ['2023-01-28-mysql-compression-no-encryption.sql.gz', GzipCompressor::class],
    ['2023-01-28-mysql-compression-no-encryption.sql.bz2', Bzip2Compressor::class],
]);

it('falls back to the extension when the file has no magic bytes', function () {
    $empty = sys_get_temp_dir().'/lbr-empty-'.uniqid().'.gz';
    touch($empty);

    try {
        expect(CompressorFactory::forDumpFile($empty))->toBeInstanceOf(GzipCompressor::class);
    } finally {
        unlink($empty);
    }
});

it('throws when the dump file does not exist', function () {
    CompressorFactory::forDumpFile('/does/not/exist.sql');
})->throws(CannotStartImport::class);

it('streams the whole dump, matching the uncompressed fixture', function (string $compressed, string $plain) {
    $stream = CompressorFactory::forDumpFile(lbrFixture($compressed))->open(lbrFixture($compressed));

    $contents = '';
    while (! feof($stream)) {
        $contents .= fread($stream, 8192);
    }
    fclose($stream);

    expect($contents)->toBe(file_get_contents(lbrFixture($plain)));
})->with([
    ['2023-01-28-mysql-compression-no-encryption.sql.gz', '2023-01-28-mysql-no-compression-no-encryption.sql'],
    ['2023-01-28-mysql-compression-no-encryption.sql.bz2', '2023-01-28-mysql-no-compression-no-encryption.sql'],
    ['2023-03-04-pgsql-compression-no-encryption.sql.gz', '2023-03-04-pgsql-no-compression-no-encryption.sql'],
    ['2023-02-28-sqlite-compression-no-encryption.sql.gz', '2023-02-28-sqlite-no-compression-no-encryption.sql'],
]);

it('names ext-bz2 when a bzip2 dump is handed in without the extension', function () {
    if (extension_loaded('bz2')) {
        expect(true)->toBeTrue();

        return;
    }

    expect(fn () => (new Bzip2Compressor)->open(lbrFixture('2023-01-28-mysql-compression-no-encryption.sql.bz2')))
        ->toThrow(CannotStartImport::class, 'ext-bz2');
});
