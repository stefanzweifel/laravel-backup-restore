<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Illuminate\Support\Facades\File;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

class Sqlite extends DbImporter
{
    /**
     * @throws ImportFailed
     */
    public function getImportCommand(string $dumpFile, string $connection): string
    {
        if (str($dumpFile)->endsWith('sql')) {
            return 'sqlite3 '.escapeshellarg(config("database.connections.{$connection}.database")).' < '.escapeshellarg($dumpFile);
        }

        // @todo: Improve detection of compressed files
        $decompressCommand = match (File::extension($dumpFile)) {
            'gz' => 'gunzip -c '.escapeshellarg($dumpFile),
            'bz2' => 'bunzip2 -c '.escapeshellarg($dumpFile),
            default => throw ImportFailed::decompressionFailed($dumpFile, 'Unknown compression format'),
        };

        return $decompressCommand.' | sqlite3 '.escapeshellarg(config("database.connections.{$connection}.database"));
    }

    public function getCliName(): string
    {
        return 'gunzip';
    }
}
