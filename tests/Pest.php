<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
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
