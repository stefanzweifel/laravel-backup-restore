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
    public function getImportCommand(string $dumpFile): string
    {
        /** @var \Spatie\DbDumper\Databases\PostgreSql $dumper */
        $dumper = DbDumperFactory::createFromConnection('pgsql');
        $dumper->getContentsOfCredentialsFile();

        if (str($dumpFile)->endsWith('gz')) {
            return 'gunzip -c '.$dumpFile.' | psql -U '.config('database.connections.pgsql.username').' -d '.config('database.connections.pgsql.database');
        }

        return 'psql -U '.config('database.connections.pgsql.username').' -d '.config('database.connections.pgsql.database').' < '.$dumpFile;
    }
}
