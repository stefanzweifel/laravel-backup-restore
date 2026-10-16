<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;

class CliNotFound extends Exception implements BackupRestoreException
{
    protected function __construct(
        public readonly string $cli,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function create(string $cli): self
    {
        return new self(
            cli: $cli,
            message: "The \"{$cli}\" binary was not found.",
        );
    }

    public function hint(): ?string
    {
        return "Install {$this->cli} and make sure it is on the PATH of the PHP process, or set import_binary_path in config/backup-restore.php to the directory it is in.";
    }
}
