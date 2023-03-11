<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

class NoBackupsFound extends \Exception
{
    public static function onDisk(string $disk): self
    {
        return new static("No backups found on disk {$disk}.");
    }
}
