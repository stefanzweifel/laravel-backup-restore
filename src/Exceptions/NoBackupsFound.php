<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;
use Illuminate\Support\Facades\Config;

class NoBackupsFound extends Exception implements BackupRestoreException
{
    protected function __construct(
        public readonly string $disk,
        public readonly ?string $backupName,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function onDisk(string $disk, ?string $backupName = null): self
    {
        return new self(
            disk: $disk,
            backupName: $backupName ?? Config::string('backup.backup.name'),
            message: "No backups found on disk \"{$disk}\".",
        );
    }

    public function hint(): ?string
    {
        if ($this->backupName === null) {
            return 'Looked for *.zip files on that disk.';
        }

        return "Looked for *.zip files under \"{$this->backupName}\" on that disk. Pass --backup with a path to restore a backup stored somewhere else.";
    }
}
