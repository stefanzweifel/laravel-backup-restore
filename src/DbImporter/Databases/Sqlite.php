<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Databases;

use PDO;
use PDOException;
use Wnx\LaravelBackupRestore\DbImporter\DbImporter;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\ImportAborted;
use Wnx\LaravelBackupRestore\DbImporter\Exceptions\ImportFailed;
use Wnx\LaravelBackupRestore\DbImporter\Support\SqliteStatementReader;

/**
 * Imports through PDO instead of the `sqlite3` binary. Besides dropping the
 * binary requirement, this avoids a hole: `sqlite3` runs `.shell` and
 * `.system` lines it reads from stdin, so a dump file could execute shell
 * commands. PDO has no dot-commands and treats such a line as a syntax error.
 *
 * The dump is read in chunks and executed one statement at a time, so peak
 * memory follows the widest single statement rather than the size of the file.
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
        // Before the database file is created by the PDO constructor, and before
        // the dump is opened. A dump that yields no statements never enters the
        // loop below, so without this an aborted import would report success.
        if ($this->abort?->wasRequested()) {
            throw ImportAborted::bySignal($this->abort->signal() ?? 0);
        }

        try {
            $connection = new PDO('sqlite:'.$this->dbName, options: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $exception) {
            throw ImportFailed::statementFailed($exception->getMessage());
        }

        $statements = (new SqliteStatementReader)->pipe($this->openDumpStream($dumpFile));
        $number = 0;

        // The dump carries its own BEGIN TRANSACTION and COMMIT, and the
        // statements run in the order it writes them. Opening a transaction
        // around the loop instead would leave the PRAGMA lines that come
        // before it without effect, because SQLite ignores PRAGMA
        // foreign_keys once a transaction is open.
        foreach ($statements as $statement) {
            // PDO::exec() blocks in C, so a single very large statement still runs to
            // completion. Checking here is the finest granularity available.
            if ($this->abort?->wasRequested()) {
                throw ImportAborted::bySignal($this->abort->signal() ?? 0);
            }

            $number++;

            try {
                $connection->exec($statement);
            } catch (PDOException $exception) {
                throw ImportFailed::statementInDumpFailed($number, $statement, $exception->getMessage());
            }
        }
    }
}
