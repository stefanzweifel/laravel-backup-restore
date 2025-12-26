<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Illuminate\Support\Facades\File;
use Spatie\Backup\Exceptions\CannotCreateDbDumper;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

class PostgreSql extends DbImporter
{
    /**
     * @throws CannotCreateDbDumper|ImportFailed
     */
    public function getImportCommand(string $dumpFile, string $connection): string
    {
        if (config("database.connections.{$connection}.dump.dump_binary_path")) {
            $this->setDumpBinaryPath(config("database.connections.{$connection}.dump.dump_binary_path"));
        }

        /** @var \Spatie\DbDumper\Databases\PostgreSql $dumper */
        $dumper = DbDumperFactory::createFromConnection($connection);
        $dumper->getContentsOfCredentialsFile();

        $username = config("database.connections.{$connection}.username");
        $password = config("database.connections.{$connection}.password");
        $host = config("database.connections.{$connection}.host");
        $port = config("database.connections.{$connection}.port");
        $database = config("database.connections.{$connection}.database");
        if (str($dumpFile)->endsWith('sql')) {
            return collect([
                $this->dumpBinaryPath.'psql',
                'postgresql://'.
                urldecode($username).':'.
                urlencode($password).'@'.
                $host.':'.
                $port.'/'.
                $database,
                '< '.$dumpFile,
            ])->implode(' ');
        }

        if ($this->isBinaryDump($dumpFile)) {
            return sprintf(
                'pg_restore --verbose --no-owner --host=%s --port=%s --username=%s --dbname=%s %s',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($database),
                escapeshellarg($dumpFile)
            );
        }

        // @todo: Improve detection of compressed files
        $decompressCommand = match (File::extension($dumpFile)) {
            'gz' => "gunzip -c {$dumpFile}",
            'bz2' => "bunzip2 -c {$dumpFile}",
            default => throw ImportFailed::decompressionFailed($dumpFile, 'Unknown compression format'),
        };

        return collect([
            $decompressCommand,
            '|',
            $this->dumpBinaryPath.'psql',
            'postgresql://'.
            urldecode($username).':'.
            urldecode($password).'@'.
            $host.':'.
            $port.'/'.
            $database,
        ])->implode(' ');
    }

    public function getCliName(): string
    {
        return 'psql';
    }

    public function isBinaryDump(string $dumpFile): bool
    {
        return str($dumpFile)->endsWith([
            '.backup',
        ]);
    }
}
