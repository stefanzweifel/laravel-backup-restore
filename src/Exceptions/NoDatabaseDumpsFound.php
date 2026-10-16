<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;
use Wnx\LaravelBackupRestore\PendingRestore;

class NoDatabaseDumpsFound extends Exception implements BackupRestoreException
{
    /**
     * @param  list<string>  $filesInBackup
     */
    protected function __construct(
        public readonly string $backup,
        public readonly array $filesInBackup,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function notFoundInBackup(PendingRestore $pendingRestore): self
    {
        return new self(
            backup: $pendingRestore->backup,
            filesInBackup: array_values($pendingRestore->getAvailableFilesInDbDumpsDirectory()->all()),
            message: "The backup \"{$pendingRestore->backup}\" contains no database dumps.",
        );
    }

    public function hint(): ?string
    {
        $configHint = 'Check that the backup was created with a database source configured in config/backup.php.';

        if ($this->filesInBackup === []) {
            return "The archive's db-dumps directory is empty or missing. ".$configHint;
        }

        $files = implode(', ', array_map(
            static fn (string $file): string => basename($file),
            $this->filesInBackup
        ));

        return "The archive's db-dumps directory contains: {$files}. ".$configHint;
    }
}
