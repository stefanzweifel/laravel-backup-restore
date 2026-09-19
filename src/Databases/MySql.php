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
        $decompressCommand = match (File::extension($storagePathToDatabaseFile)) {
            'gz' => 'gunzip < '.escapeshellarg($storagePathToDatabaseFile),
            'bz2' => 'bunzip2 -c '.escapeshellarg($storagePathToDatabaseFile),
            default => throw ImportFailed::decompressionFailed($storagePathToDatabaseFile, 'Unknown compression format'),
        };

        return collect([
            $decompressCommand,
            '|',
            ...$this->getMySqlArguments($importToDatabase, $credentials, $connection),
        ])->filter()->implode(' ');
    }

    private function getMySqlImportCommandForUncompressedDump(string $importToDatabase, string $storagePathToDatabaseFile, array $credentials, string $connection): string
    {
        return collect([
            ...$this->getMySqlArguments($importToDatabase, $credentials, $connection),
            '<',
            escapeshellarg($storagePathToDatabaseFile),
        ])->filter()->implode(' ');
    }

    /**
     * Every value here ends up in a string that is handed to the shell, so each one
     * is escaped individually.
     *
     * @return array<int, string>
     */
    private function getMySqlArguments(string $importToDatabase, array $credentials, string $connection): array
    {
        return [
            escapeshellarg($this->dumpBinaryPath.'mysql'),
            filled($credentials['user'] ?? null) ? '-u '.escapeshellarg((string) $credentials['user']) : '',
            filled($credentials['password'] ?? null) ? '-p'.escapeshellarg((string) $credentials['password']) : '',
            filled($credentials['port'] ?? null) ? '-P '.escapeshellarg((string) $credentials['port']) : '',
            filled($credentials['host'] ?? null) ? '-h '.escapeshellarg((string) $credentials['host']) : '',
            escapeshellarg($importToDatabase),
            $this->getOptions($connection),
        ];
    }

    /**
     * The configured options are a single string holding one or more CLI flags. Split
     * it into arguments — honouring quoted values that contain spaces — and escape
     * each argument, so no option value can break out into the shell.
     */
    private function getOptions(string $connection): string
    {
        $options = (string) config("database.connections.{$connection}.dump.options", '');

        preg_match_all('/(?:[^\s"\']+|"[^"]*"|\'[^\']*\')+/', $options, $matches);

        return collect($matches[0])
            ->map(fn (string $option): string => escapeshellarg(
                preg_replace_callback(
                    '/"([^"]*)"|\'([^\']*)\'/',
                    fn (array $match): string => $match[2] ?? $match[1],
                    $option
                ) ?? $option
            ))
            ->implode(' ');
    }
}
