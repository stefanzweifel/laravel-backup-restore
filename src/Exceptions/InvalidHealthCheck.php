<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;
use Wnx\LaravelBackupRestore\HealthChecks\HealthCheck;

class InvalidHealthCheck extends Exception implements BackupRestoreException
{
    protected function __construct(
        public readonly string $healthCheck,
        public readonly string $configKey,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function notAHealthCheck(string $healthCheck, string $configKey = 'backup-restore.health-checks'): self
    {
        return new self(
            healthCheck: $healthCheck,
            configKey: $configKey,
            message: "\"{$healthCheck}\" is configured in {$configKey} but is not a health check.",
        );
    }

    public function hint(): ?string
    {
        return "Every entry in {$this->configKey} must be a class extending ".HealthCheck::class.'.';
    }
}
