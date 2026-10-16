<?php

declare(strict_types=1);

use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

/**
 * Waits until the probe has written $needle to stdout. A deadline, so a line
 * that never arrives fails the test instead of blocking the suite.
 */
function lbrWaitForProbeOutput(Process $probe, string $needle, string $failure, float $seconds = 10.0): void
{
    $deadline = microtime(true) + $seconds;

    while (! str_contains($probe->getOutput(), $needle)) {
        if (microtime(true) > $deadline) {
            $probe->stop(0);

            throw new RuntimeException($failure);
        }

        usleep(50_000);
    }
}

/**
 * Starts the probe and waits until it reports that its child is running.
 */
function lbrStartSignalProbe(): Process
{
    $probe = new Process(['php', __DIR__.'/signal-probe.php']);

    // A deadline on the probe itself, so a handler that never runs fails the
    // test instead of blocking the suite. Symfony stops the process before it
    // throws ProcessTimedOutException.
    $probe->setTimeout(20.0);
    $probe->start();

    // stop(0) ends the probe but not its own `sleep 30` grandchild, if it got
    // that far. That one is bounded by the 30 seconds it sleeps for.
    lbrWaitForProbeOutput($probe, 'ready', 'The probe never started its child process.');

    return $probe;
}

it('runs the signal handler while blocked in Process::wait()', function () {
    $probe = lbrStartSignalProbe();

    try {
        $pid = $probe->getPid();
        expect($pid)->not->toBeNull();

        posix_kill($pid, SIGTERM);

        // The child sleeps for 30 seconds. Returning well inside that means the
        // handler ran and stopped it rather than the sleep finishing.
        $probe->wait();

        expect($probe->getOutput())->toContain('aborted');
        expect($probe->getExitCode())->toBe(143);
    } finally {
        $probe->stop(0);
    }
})
    ->skip(fn () => ! extension_loaded('pcntl') || ! extension_loaded('posix'), 'Requires ext-pcntl and ext-posix.')
    ->skip(fn () => windows_os(), 'Signals are not available on Windows.');

it('dies on the second signal instead of aborting again', function () {
    $probe = lbrStartSignalProbe();

    try {
        $pid = $probe->getPid();
        expect($pid)->not->toBeNull();

        // The probe stays busy after the first signal, so the second one has to
        // end it outright. Standard signals do not queue: wait for the handler
        // to report that it ran, or under load both deliveries collapse into one
        // and the probe aborts once.
        posix_kill($pid, SIGTERM);

        lbrWaitForProbeOutput($probe, 'aborting', 'The probe never handled the first signal.');

        posix_kill($pid, SIGTERM);

        try {
            $probe->wait();
        } catch (ProcessSignaledException) {
            // Expected: the probe died of the signal, and Symfony reports that as
            // an exception for a signal it did not send itself.
        }

        // Killed by an uncaught signal: no PHP exit, so no "aborted" line.
        expect($probe->getTermSignal())->toBe(SIGTERM);
        expect($probe->getOutput())->not->toContain('aborted');
    } finally {
        $probe->stop(0);
    }
})
    ->skip(fn () => ! extension_loaded('pcntl') || ! extension_loaded('posix'), 'Requires ext-pcntl and ext-posix.')
    ->skip(fn () => windows_os(), 'Signals are not available on Windows.');
