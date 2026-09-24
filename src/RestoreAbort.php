<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore;

use Closure;
use Throwable;

/**
 * Carries the fact that a restore was interrupted, and the means to stop
 * whatever is currently blocking.
 *
 * Long-running work registers a stopper with whileRunning(). A signal handler
 * calls requestAbort(), which runs those stoppers so that the blocking call
 * returns and the stack can unwind through the normal error handling.
 *
 * Deliberately free of any dependency: it is touched from inside a signal
 * handler, and from both sides of the framework-agnostic importer boundary.
 */
final class RestoreAbort
{
    private ?int $signal = null;

    /** @var array<int, callable(): void> */
    private array $stoppers = [];

    private int $nextStopperId = 0;

    /**
     * Records the signal and runs every stopper. Safe to call more than once:
     * the first signal is the one that is kept and no stopper runs twice.
     */
    public function requestAbort(int $signal): void
    {
        $this->signal ??= $signal;

        $stoppers = $this->stoppers;
        $this->stoppers = [];

        foreach ($stoppers as $stopper) {
            $this->run($stopper);
        }
    }

    public function wasRequested(): bool
    {
        return $this->signal !== null;
    }

    public function signal(): ?int
    {
        return $this->signal;
    }

    /**
     * Registers a way to stop blocking work, and returns a closure that
     * de-registers it again. A stopper registered after the abort was already
     * requested runs immediately.
     *
     * @param  callable(): void  $stop
     * @return Closure(): void
     */
    public function whileRunning(callable $stop): Closure
    {
        if ($this->wasRequested()) {
            $this->run($stop);

            return static function (): void {};
        }

        $id = $this->nextStopperId++;
        $this->stoppers[$id] = $stop;

        return function () use ($id): void {
            unset($this->stoppers[$id]);
        };
    }

    /**
     * A stopper that fails must not stop the others, and must not replace the
     * abort with an exception thrown out of a signal handler.
     *
     * @param  callable(): void  $stop
     */
    private function run(callable $stop): void
    {
        try {
            $stop();
        } catch (Throwable) {
            // The process is already gone, or was never started. Either way
            // there is nothing left to stop.
        }
    }
}
