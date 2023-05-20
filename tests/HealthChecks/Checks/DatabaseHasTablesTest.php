<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\HealthChecks\Checks\DatabaseHasTables;
use Wnx\LaravelBackupRestore\PendingRestore;

it('returns failed result if database for given connection is empty', function () {

    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/2023-01-28-mysql-no-compression-no-encryption.zip',
        connection: 'mysql',
        backupPassword: null,
    );

    $result = (new DatabaseHasTables)->run($pendingRestore);

    expect($result)->status->toBe(Command::FAILURE);
});

it('returns successful result if database contains tables', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/2023-01-28-mysql-no-compression-no-encryption.zip',
        connection: 'mysql',
        backupPassword: null,
    );

    // Add a table to the database
    DB::connection('mysql')
        ->getSchemaBuilder()
        ->create('test', function ($table) {
            $table->id();
        });

    $result = (new DatabaseHasTables)->run($pendingRestore);

    expect($result)->status->toBe(Command::SUCCESS);
});
