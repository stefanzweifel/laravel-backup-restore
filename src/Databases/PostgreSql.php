<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Symfony\Component\Process\Process;
use Wnx\LaravelBackupRestore\DbImporter;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;

class PostgreSql extends DbImporter
{
    /**
     * @throws ImportFailed
     */
    public function importToDatabase(string $dumpFile): void
    {
        $process = $this->getProcess($dumpFile);

        $process->run();

        $this->checkIfImportWasSuccessful($process, $dumpFile);
    }

    public function getImportCommand(string $dumpFile): string
    {
        $dumper = DbDumperFactory::createFromConnection('pgsql');
        $dumper->getContentsOfCredentialsFile();

        if (str($dumpFile)->endsWith('gz')) {
            return 'gunzip -c '.$dumpFile.' | psql -U '.config('database.connections.pgsql.username').' -d '.config('database.connections.pgsql.database');
        }

        return 'psql -U '.config('database.connections.pgsql.username').' -d '.config('database.connections.pgsql.database').' < '.$dumpFile;
    }

    private function getProcess(string $dumpFile): Process
    {
        $command = $this->getImportCommand($dumpFile);

        return Process::fromShellCommandline($command, null, null, null, 0);
    }
}
