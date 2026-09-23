<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Databases;

use PDO;
use PDOException;
use Wnx\LaravelBackupRestore\DbImporter\DbImporter;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\ImportFailed;

/**
 * Imports through PDO instead of the `sqlite3` binary. Besides dropping the
 * binary requirement, this avoids a hole: `sqlite3` runs `.shell` and
 * `.system` lines it reads from stdin, so a dump file could execute shell
 * commands. PDO has no dot-commands and treats such a line as a syntax error.
 *
 * The dump is read into memory in one piece, which is fine for the SQLite
 * dumps this package deals with. A dump of a few hundred megabytes would need
 * a chunked reader instead.
 */
class Sqlite extends DbImporter
{
    public function getBinaryName(): string
    {
        return 'sqlite3';
    }

    /**
     * SQLite runs no external command, so there is nothing to return.
     *
     * @return array<int, string>
     */
    public function getImportCommand(): array
    {
        return [];
    }

    protected function prepareImport(string $dumpFile): void
    {
        $this->guardAgainstMissingDbName();
    }

    protected function runImport(string $dumpFile): void
    {
        $stream = $this->openDumpStream($dumpFile);

        try {
            $dump = stream_get_contents($stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($dump === false) {
            throw ImportFailed::statementFailed("Could not read the dump file `{$dumpFile}`.");
        }

        try {
            $connection = new PDO('sqlite:'.$this->dbName, options: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $connection->exec($dump);
        } catch (PDOException $exception) {
            throw ImportFailed::statementFailed($exception->getMessage());
        }
    }
}
