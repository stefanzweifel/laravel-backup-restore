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
                escapeshellarg($this->dumpBinaryPath.'psql'),
                escapeshellarg($this->getConnectionString($username, $password, $host, $port, $database)),
                '< '.escapeshellarg($dumpFile),
            ])->implode(' ');
        }

        if ($this->isBinaryDump($dumpFile)) {
            return sprintf(
                'pg_restore --verbose --no-owner --host=%s --port=%s --username=%s --dbname=%s %s',
                escapeshellarg((string) $host),
                escapeshellarg((string) $port),
                escapeshellarg((string) $username),
                escapeshellarg((string) $database),
                escapeshellarg($dumpFile)
            );
        }

        // @todo: Improve detection of compressed files
        $decompressCommand = match (File::extension($dumpFile)) {
            'gz' => 'gzip -d -c '.escapeshellarg($dumpFile),
            'bz2' => 'bunzip2 -c '.escapeshellarg($dumpFile),
            default => throw ImportFailed::decompressionFailed($dumpFile, 'Unknown compression format'),
        };

        return collect([
            $decompressCommand,
            '|',
            escapeshellarg($this->dumpBinaryPath.'psql'),
            escapeshellarg($this->getConnectionString($username, $password, $host, $port, $database)),
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

    /**
     * The connection string is handed to the shell as a single argument, so it is
     * escaped as a whole. The values that make up its userinfo and path are
     * percent-encoded on top of that, so they cannot alter the URI either.
     */
    private function getConnectionString(mixed $username, mixed $password, mixed $host, mixed $port, mixed $database): string
    {
        return 'postgresql://'.
            rawurlencode((string) $username).':'.
            rawurlencode((string) $password).'@'.
            $host.':'.
            $port.'/'.
            rawurlencode((string) $database);
    }
}
