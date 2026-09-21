<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;

class DumpIsNotRestorable extends Exception implements BackupRestoreException
{
    protected function __construct(
        public readonly string $dumpFile,
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function isEmpty(string $dumpFile): self
    {
        return new self(
            dumpFile: $dumpFile,
            reason: 'empty',
            message: "The database dump \"{$dumpFile}\" is empty.",
        );
    }

    public static function containsNoStatements(string $dumpFile): self
    {
        return new self(
            dumpFile: $dumpFile,
            reason: 'no-statements',
            message: "The database dump \"{$dumpFile}\" contains no CREATE TABLE or INSERT statements.",
        );
    }

    public function hint(): ?string
    {
        return 'The backup is truncated or was created against an empty database. No tables were dropped. Restore a different backup, or run without --reset to import it anyway.';
    }
}
