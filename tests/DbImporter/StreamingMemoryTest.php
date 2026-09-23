<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * The dump is pumped into the client process without being held in memory.
 * These generate a dump much larger than the committed fixtures, which are all
 * under 11 KB, and check that peak memory does not follow the file size.
 */
function generateLargeDump(string $path, int $rows, bool $gzip = false): int
{
    $handle = fopen($gzip ? 'compress.zlib://'.$path : $path, 'wb');

    fwrite($handle, "DROP TABLE IF EXISTS lbr_large;\n");
    fwrite($handle, "CREATE TABLE lbr_large (id int, payload text);\n");

    $payload = str_repeat('x', 900);

    for ($id = 1; $id <= $rows; $id++) {
        fwrite($handle, "INSERT INTO lbr_large VALUES ({$id}, '{$payload}');\n");
    }

    fclose($handle);

    return $rows;
}

it('imports a 16 MB mysql dump without holding it in memory', function () {
    $dump = tempnam(sys_get_temp_dir(), 'lbr-large-').'.sql';
    $rows = generateLargeDump($dump, 17000);

    expect(filesize($dump))->toBeGreaterThan(15 * 1024 * 1024);

    try {
        gc_collect_cycles();
        $before = memory_get_peak_usage(true);

        mysqlImporter()->importFromFile($dump);

        $growth = memory_get_peak_usage(true) - $before;

        expect(DB::connection('mysql-restore')->table('lbr_large')->count())->toBe($rows);
        expect($growth)->toBeLessThan(4 * 1024 * 1024);
    } finally {
        unlink($dump);
        DB::connection('mysql-restore')->statement('DROP TABLE IF EXISTS lbr_large');
    }
});

it('imports a large gzipped pgsql dump without holding it in memory', function () {
    $dump = tempnam(sys_get_temp_dir(), 'lbr-large-').'.sql.gz';
    $rows = generateLargeDump($dump, 17000, gzip: true);

    try {
        gc_collect_cycles();
        $before = memory_get_peak_usage(true);

        pgsqlImporter()->importFromFile($dump);

        $growth = memory_get_peak_usage(true) - $before;

        expect(DB::connection('pgsql')->table('lbr_large')->count())->toBe($rows);
        expect($growth)->toBeLessThan(4 * 1024 * 1024);
    } finally {
        unlink($dump);
        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS lbr_large');
    }
})->group('pgsql');
