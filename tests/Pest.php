<?php

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\Tests\TestCase;
use function Pest\Laravel\artisan;


uses(TestCase::class)
    ->beforeEach(function () {
        // Delete all files in the temp directory
        Storage::disk('local')->deleteDirectory('backup-restore-temp');


        // Wipe all databases before each test
        artisan('db:wipe', [
            '--database' => 'mysql',
        ]);

    })
    ->afterEach(function () {
        // Wipe all databases after each test
        artisan('db:wipe', [
            '--database' => 'mysql',
        ]);

         // Delete all files in the temp directory
         Storage::disk('local')->deleteDirectory('backup-restore-temp');
    })
    ->in(__DIR__);



