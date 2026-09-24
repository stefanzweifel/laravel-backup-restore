<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;

class CannotOpenBackup extends Exception implements BackupRestoreException
{
    protected function __construct(
        public readonly string $disk,
        public readonly string $backup,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function onDisk(string $disk, string $backup): self
    {
        return new self(
            disk: $disk,
            backup: $backup,
            message: "Could not open \"{$backup}\" on disk \"{$disk}\" for reading.",
        );
    }

    public function hint(): ?string
    {
        return 'Check that the backup still exists on that disk and that the credentials for it grant read access.';
    }
}
