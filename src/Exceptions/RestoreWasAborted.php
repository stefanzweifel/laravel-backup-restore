<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;

class RestoreWasAborted extends Exception implements BackupRestoreException
{
    /**
     * Signal numbers differ between platforms, so the constants are read at
     * runtime rather than hard-coded. They only exist with ext-pcntl.
     *
     * @var array<string, int>
     */
    private const SIGNAL_CONSTANTS = [
        'SIGHUP' => 1,
        'SIGINT' => 2,
        'SIGQUIT' => 3,
        'SIGTERM' => 15,
    ];

    protected function __construct(
        public readonly int $signal,
        public readonly bool $databaseWasTouched,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function bySignal(int $signal, bool $databaseWasTouched): self
    {
        return new self(
            signal: $signal,
            databaseWasTouched: $databaseWasTouched,
            message: 'The restore was interrupted by '.self::nameOf($signal).'.',
        );
    }

    /**
     * The conventional shell exit code for a process killed by a signal.
     */
    public function exitCode(): int
    {
        return 128 + $this->signal;
    }

    public function hint(): ?string
    {
        if ($this->databaseWasTouched) {
            return 'The database holds a partial restore. The downloaded files were kept so the restore can be re-run without downloading the backup again.';
        }

        return 'The database was not touched. The downloaded files were removed.';
    }

    private static function nameOf(int $signal): string
    {
        foreach (self::SIGNAL_CONSTANTS as $name => $number) {
            if ($number === $signal) {
                return $name;
            }
        }

        return "signal {$signal}";
    }
}
