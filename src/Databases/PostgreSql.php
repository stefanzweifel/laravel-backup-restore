<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Spatie\Backup\Exceptions\CannotCreateDbDumper;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;

class PostgreSql extends DbImporter
{
    /**
     * @throws CannotCreateDbDumper
     */
    public function getImportCommand(string $dumpFile, string $connection): string
    {
        if (config("database.connections.{$connection}.dump.dump_binary_path")) {
            $this->setDumpBinaryPath(config("database.connections.{$connection}.dump.dump_binary_path"));
        }

        /** @var \Spatie\DbDumper\Databases\PostgreSql $dumper */
        $dumper = DbDumperFactory::createFromConnection($connection);
        $dumper->getContentsOfCredentialsFile();

        // @todo: Improve detection of compressed files
        if (str($dumpFile)->endsWith('gz')) {
            return collect([
                'gunzip -c '.$dumpFile,
                '|',
                $this->dumpBinaryPath.'psql',
                '-U '.config("database.connections.{$connection}.username"),
                '-d '.config("database.connections.{$connection}.database"),
            ])->implode(' ');
        }

        return collect([
            $this->dumpBinaryPath.'psql',
            '-U '.config("database.connections.{$connection}.username"),
            '-d '.config("database.connections.{$connection}.database"),
            '< '.$dumpFile,
        ])->implode(' ');
    }

    public function getCliName(): string
    {
        return 'psql';
    }
}
