<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\PendingRestore;
use Wnx\LaravelBackupRestore\Tests\TestCase;

use function Pest\Laravel\artisan;

uses(TestCase::class)
    ->beforeEach(function () {
        // Delete all files in the temp directory
        Storage::disk('local')->deleteDirectory('backup-restore-temp');

        // Wipe all databases before each test
        artisan('db:wipe', ['--database' => 'mysql']);
        artisan('db:wipe', ['--database' => 'sqlite']);
        artisan('db:wipe', ['--database' => 'pgsql']);
        artisan('db:wipe', ['--database' => 'pgsql-restore']);
    })
    ->afterEach(function () {
        // Wipe all databases after each test
        artisan('db:wipe', ['--database' => 'mysql']);
        artisan('db:wipe', ['--database' => 'sqlite']);
        artisan('db:wipe', ['--database' => 'pgsql']);
        artisan('db:wipe', ['--database' => 'pgsql-restore']);

        // Delete all files in the temp directory
        Storage::disk('local')->deleteDirectory('backup-restore-temp');
    })
    ->in(__DIR__);

/**
 * Build a ZIP in the test itself and put it where the restore expects the
 * downloaded archive, so no binary fixture has to be committed.
 */
function putCraftedArchive(PendingRestore $pendingRestore, Closure $build): void
{
    $tmpPath = tempnam(sys_get_temp_dir(), 'lbr-test-').'.zip';

    $zip = new ZipArchive;
    $zip->open($tmpPath, ZipArchive::CREATE);
    $build($zip);
    $zip->close();

    Storage::disk('local')->put(
        $pendingRestore->getPathToLocalCompressedBackup(),
        file_get_contents($tmpPath)
    );

    unlink($tmpPath);
}
