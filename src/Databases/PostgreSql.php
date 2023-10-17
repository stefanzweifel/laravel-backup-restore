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
        /** @var \Spatie\DbDumper\Databases\PostgreSql $dumper */
        $dumper = DbDumperFactory::createFromConnection($connection);
        $dumper->getContentsOfCredentialsFile();

        // @todo: Improve detection of compressed files
        if (str($dumpFile)->endsWith('gz')) {
            return 'gunzip -c '.$dumpFile.' | psql -U '.config("database.connections.{$connection}.username").' -d '.config("database.connections.{$connection}.database");
        }

        return 'psql -U '.config("database.connections.{$connection}.username").' -d '.config("database.connections.{$connection}.database").' < '.$dumpFile;
    }

    public function getCliName(): string
    {
        return 'psql';
    }
}
