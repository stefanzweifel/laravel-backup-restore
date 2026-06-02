<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\PendingRestore;

it('returns only dump files whose basenames contain safe characters', function () {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/test.zip',
        connection: 'mysql',
    );

    $dbDumpsPath = $pendingRestore->getPathToLocalDecompressedBackup().DIRECTORY_SEPARATOR.'db-dumps';

    Storage::disk('local')->put($dbDumpsPath.DIRECTORY_SEPARATOR.'legitimate.sql', '-- SQL');
    Storage::disk('local')->put($dbDumpsPath.DIRECTORY_SEPARATOR.'also-fine_2024.sql.gz', '-- SQL');

    $dumps = $pendingRestore->getAvailableDbDumps();

    expect($dumps)->toHaveCount(2);
});

it('excludes dump files whose basename contains shell metacharacters (CWE-78)', function (string $maliciousName) {
    $pendingRestore = PendingRestore::make(
        disk: 'remote',
        backup: 'Laravel/test.zip',
        connection: 'mysql',
    );

    $dbDumpsPath = $pendingRestore->getPathToLocalDecompressedBackup().DIRECTORY_SEPARATOR.'db-dumps';

    Storage::disk('local')->put($dbDumpsPath.DIRECTORY_SEPARATOR.'legitimate.sql', '-- SQL');
    Storage::disk('local')->put($dbDumpsPath.DIRECTORY_SEPARATOR.$maliciousName, '-- SQL');

    $dumps = $pendingRestore->getAvailableDbDumps();

    expect($dumps)->toHaveCount(1)
        ->and($dumps->values()->first())->toEndWith('legitimate.sql');
})->with([
    'semicolon injection' => ['backup.sql;touch /tmp/lbr_pwned;#.sql'],
    'pipe injection' => ['backup|whoami.sql'],
    'command substitution' => ['backup$(id).sql'],
    'backtick substitution' => ['backup`id`.sql'],
    'space in name' => ['backup file.sql'],
    'ampersand injection' => ['backup&id.sql'],
]);
