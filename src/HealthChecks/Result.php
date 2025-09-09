<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\HealthChecks;

use Illuminate\Console\Command;

class Result
{
    public function __construct(
        public readonly HealthCheck $healthCheck,
        public int $status,
        public ?string $message,
    ) {
        //
    }

    public static function make(HealthCheck $healthCheck): self
    {
        return new self(healthCheck: $healthCheck, status: Command::SUCCESS, message: null);
    }

    public function ok(): self
    {
        $this->status = Command::SUCCESS;

        $this->message = null;

        return $this;
    }

    public function failed(?string $message = null): self
    {
        $this->status = Command::FAILURE;

        $this->message = $message;

        return $this;
    }
}
