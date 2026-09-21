<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Databases;

use Wnx\LaravelBackupRestore\DbImporterFactory;

/**
 * @deprecated Use Wnx\LaravelBackupRestore\DbImporter\Databases\MariaDb.
 */
class MariaDb extends DbImporter
{
    public function getCliName(): string
    {
        return 'mariadb';
    }

    /**
     * @deprecated The returned string is for display only. See DbImporter::getImportCommand().
     */
    public function getImportCommand(string $dumpFile, string $connection): string
    {
        return static::commandForDisplay(
            DbImporterFactory::importerForConnection($connection)->getImportCommand()
        );
    }

    protected function forwardsToDbImporter(): bool
    {
        return true;
    }
}
