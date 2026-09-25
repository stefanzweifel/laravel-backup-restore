<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;
use Throwable;

class RestoreWasAborted extends Exception implements BackupRestoreException
{
    /**
     * These four signal numbers are the same on every POSIX platform, so
     * hard-coding them is safe. The map exists only to name a signal without
     * referencing the SIGINT, SIGTERM and SIGHUP constants, which need ext-pcntl.
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
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /**
     * $previous carries the failure the import reported before the signal was
     * seen, where there was one, so it is not lost.
     */
    public static function bySignal(int $signal, bool $databaseWasTouched, ?Throwable $previous = null): self
    {
        return new self(
            signal: $signal,
            databaseWasTouched: $databaseWasTouched,
            message: 'The restore was interrupted by '.self::nameOf($signal).'.',
            previous: $previous,
        );
    }

    /**
     * The conventional shell exit code for a process killed by a signal.
     */
    public function exitCode(): int
    {
        return 128 + $this->signal;
    }

    /**
     * The database state only. What happened to the downloaded files depends on
     * --keep, which the command knows and this exception does not.
     */
    public function hint(): ?string
    {
        if ($this->databaseWasTouched) {
            return 'The database holds a partial restore. Re-run the command to start over.';
        }

        return 'The database was not touched.';
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
