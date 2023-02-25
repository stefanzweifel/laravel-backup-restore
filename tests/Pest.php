<?php

use Wnx\LaravelBackupRestore\Tests\TestCase;
use function Pest\Laravel\artisan;


uses(TestCase::class)
    ->beforeEach(function () {
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
    })
    ->in(__DIR__);



