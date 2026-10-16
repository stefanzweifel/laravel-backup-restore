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

        foreach ($stoppers as $id => $stopper) {
            $this->run($stopper, $id);
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
        $id = $this->nextStopperId++;

        if ($this->wasRequested()) {
            // run() puts it back if it threw, so the returned closure has to be
            // able to de-register it either way.
            $this->run($stop, $id);
        } else {
            $this->stoppers[$id] = $stop;
        }

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
    private function run(callable $stop, int $id): void
    {
        try {
            $stop();
        } catch (Throwable) {
            // Put it back so a later signal tries again. A first attempt can
            // fail on something temporary: a process that has not started yet,
            // or a call that cannot be made from the frame the handler
            // interrupted. A stopper whose process is really gone is harmless
            // to retry.
            $this->stoppers[$id] = $stop;
        }
    }
}
