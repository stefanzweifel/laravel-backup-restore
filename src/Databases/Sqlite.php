<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Illuminate\Support\Facades\File;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

class Sqlite extends DbImporter
{
    public function getImportCommand(string $dumpFile, string $connection): string
    {
        if (str($dumpFile)->endsWith('sql')) {
            return 'sqlite3 '.config("database.connections.{$connection}.database").' < '.$dumpFile;
        }

        // @todo: Improve detection of compressed files
        $decompressCommand = match (File::extension($dumpFile)) {
            'gz' => "gunzip -c {$dumpFile}",
            'bz2' => "bunzip2 -c {$dumpFile}",
            default => throw ImportFailed::decompressionFailed('Unknown compression format', $dumpFile),
        };

        return "$decompressCommand | sqlite3 ".config("database.connections.{$connection}.database");
    }

    public function getCliName(): string
    {
        return 'gunzip';
    }
}
