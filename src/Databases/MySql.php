<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Spatie\Backup\Exceptions\CannotCreateDbDumper;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;

class MySql extends DbImporter
{
    /**
     * @throws CannotCreateDbDumper
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

        // Build Shell Command to import a gzipped SQL file to a MySQL database
        if (str($dumpFile)->endsWith('gz')) {
            $command = $this->getMySqlImportCommandForCompressedDump($dumpFile, $importToDatabase, $credentialsArray);
        } else {
            $command = $this->getMySqlImportCommandForUncompressedDump($importToDatabase, $dumpFile, $credentialsArray);
        }

        return $command;
    }

    public function getCliName(): string
    {
        return 'mysql';
    }

    private function getMySqlImportCommandForCompressedDump(string $storagePathToDatabaseFile, string $importToDatabase, array $credentials): string
    {
        $quote = $this->determineQuote();
        $password = $credentials['password'];

        return collect([
            "gunzip < {$storagePathToDatabaseFile}",
            '|',
            "{$quote}{$this->dumpBinaryPath}mysql{$quote}",
            '-u', $credentials['user'],
            ! empty($password) ? "{$quote}-p'{$password}'{$quote}" : '',
            '-P', $credentials['port'],
            isset($credentials['host']) ? '-h '.$credentials['host'] : '',
            $importToDatabase,
        ])->filter()->implode(' ');
    }

    private function getMySqlImportCommandForUncompressedDump(string $importToDatabase, string $storagePathToDatabaseFile, array $credentials): string
    {
        $quote = $this->determineQuote();
        $password = $credentials['password'];

        return collect([
            "{$quote}{$this->dumpBinaryPath}mysql{$quote}",
            '-u', $credentials['user'],
            ! empty($password) ? "{$quote}-p'{$password}'{$quote}" : '',
            '-P', $credentials['port'],
            isset($credentials['host']) ? '-h '.$credentials['host'] : '',
            $importToDatabase,
            '<',
            $storagePathToDatabaseFile,
        ])->filter()->implode(' ');
    }
}
