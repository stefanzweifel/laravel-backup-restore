<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Illuminate\Support\Facades\File;
use Spatie\Backup\Exceptions\CannotCreateDbDumper;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

class MySql extends DbImporter
{
    /**
     * @throws CannotCreateDbDumper|ImportFailed
     */
    public function getImportCommand(string $dumpFile, string $connection): string
    {
        if (config("database.connections.{$connection}.dump.dump_binary_path")) {
            $this->setDumpBinaryPath(config("database.connections.{$connection}.dump.dump_binary_path"));
        }

        $dumper = DbDumperFactory::createFromConnection($connection);
        $importToDatabase = $dumper->getDbName();

        $credentialsArray = [
            'host' => config("database.connections.{$connection}.host"),
            'port' => config("database.connections.{$connection}.port"),
            'user' => config("database.connections.{$connection}.username"),
            'password' => config("database.connections.{$connection}.password"),
        ];

        if (str($dumpFile)->endsWith('sql')) {
            $command = $this->getMySqlImportCommandForUncompressedDump($importToDatabase, $dumpFile, $credentialsArray, $connection);
        } else {
            $command = $this->getMySqlImportCommandForCompressedDump($dumpFile, $importToDatabase, $credentialsArray, $connection);
        }

        return $command;
    }

    public function getCliName(): string
    {
        return 'mysql';
    }

    /**
     * @throws ImportFailed
     */
    private function getMySqlImportCommandForCompressedDump(string $storagePathToDatabaseFile, string $importToDatabase, array $credentials, string $connection): string
    {
        $quote = $this->determineQuote();
        $password = $credentials['password'];

        $decompressCommand = match (File::extension($storagePathToDatabaseFile)) {
            'gz' => "gunzip < {$storagePathToDatabaseFile}",
            'bz2' => "bunzip2 -c {$storagePathToDatabaseFile}",
            default => throw ImportFailed::decompressionFailed($storagePathToDatabaseFile, 'Unknown compression format'),
        };

        return collect([
            $decompressCommand,
            '|',
            "{$quote}{$this->dumpBinaryPath}mysql{$quote}",
            '-u', $credentials['user'],
            ! empty($password) ? "{$quote}-p{$password}{$quote}" : '',
            '-P', $credentials['port'],
            isset($credentials['host']) ? '-h '.$credentials['host'] : '',
            $importToDatabase,
            $this->getOptions($connection),
        ])->filter()->implode(' ');
    }

    private function getMySqlImportCommandForUncompressedDump(string $importToDatabase, string $storagePathToDatabaseFile, array $credentials, string $connection): string
    {
        $quote = $this->determineQuote();
        $password = $credentials['password'];

        return collect([
            "{$quote}{$this->dumpBinaryPath}mysql{$quote}",
            '-u', $credentials['user'],
            ! empty($password) ? "{$quote}-p{$password}{$quote}" : '',
            '-P', $credentials['port'],
            isset($credentials['host']) ? '-h '.$credentials['host'] : '',
            $importToDatabase,
            $this->getOptions($connection),
            '<',
            $storagePathToDatabaseFile,
        ])->filter()->implode(' ');
    }

    private function getOptions(string $connection): string
    {
        $options = config("database.connections.{$connection}.dump.options", null);
        $skipSSL = config("database.connections.{$connection}.dump.skip_ssl", null);

        return collect([
            $options,
            $skipSSL ? '--ssl-mode=DISABLED' : '',
        ])->filter()->implode(' ');
    }
}
