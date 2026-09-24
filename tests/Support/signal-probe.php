<?php

declare(strict_types=1);

use Symfony\Component\Console\SignalRegistry\SignalRegistry;
use Symfony\Component\Process\Process;
use Wnx\LaravelBackupRestore\RestoreAbort;

require __DIR__.'/../../vendor/autoload.php';

// Run as a standalone process by tests/Support/SignalTest.php, not by Pest. It
// does what the production path does — Symfony's SignalRegistry, a RestoreAbort,
// a stopper, a blocking child — and nothing else, so that a real signal can be
// sent to a process that is blocked inside Process::wait().

$abort = new RestoreAbort;

// The registry turns on pcntl_async_signals(), which is what makes a handler run
// while the process is blocked in stream_select(). This is the same registry
// Laravel's $this->trap() writes into.
$registry = new SignalRegistry;

// Mirrors RestoreCommand: the pid is captured where the handler is registered,
// so a forked child that inherited the trap dies of the signal instead of
// recording an abort.
$probePid = getmypid();

$dieOfSignal = static function (int $signal): never {
    pcntl_signal($signal, SIG_DFL);
    posix_kill(posix_getpid(), $signal);

    exit(128 + $signal);
};

$registry->register(SIGTERM, static function (int $signal) use ($abort, $probePid, $dieOfSignal): void {
    $pid = getmypid();

    if (! is_int($pid) || ! is_int($probePid) || $pid !== $probePid) {
        $dieOfSignal($signal);
    }

    // A second signal means the caller is done waiting.
    if ($abort->wasRequested()) {
        $dieOfSignal($signal);
    }

    $abort->requestAbort($signal);

    // Stand in for the slow cleanup the real command does after an abort, so
    // that a second signal has something to interrupt. Kept under the test's
    // own wait budget.
    usleep(3_000_000);
});

$process = new Process(['sleep', '30']);

// start() and wait() instead of run(), and posix_kill() on the pid instead of
// Process::signal(), because that is the shape DbImporter::runImport() ships:
// nothing Symfony owns may be touched from inside the handler that interrupted
// wait().
$process->start();

$pid = $process->getPid();

$release = $abort->whileRunning(static function () use ($pid): void {
    if ($pid !== null) {
        posix_kill($pid, SIGTERM);
    }
});

// Tell the parent the child is up, so it does not signal too early.
fwrite(STDOUT, "ready\n");

try {
    $process->wait();
} catch (Throwable $throwable) {
    // A child killed through posix_kill() rather than Process::signal() makes
    // wait() throw ProcessSignaledException, because Symfony only knows about
    // signals it sent itself. The shipped path reaches the same point:
    // DbImporter::runImport() turns it into CannotStartImport and
    // Databases\DbImporter::importToDatabase() turns that into
    // RestoreWasAborted whenever the abort token was set. Anything else is a
    // genuine failure and is reported as one.
    if (! $abort->wasRequested()) {
        throw $throwable;
    }
} finally {
    $release();
}

fwrite(STDOUT, $abort->wasRequested() ? "aborted\n" : "completed\n");

exit($abort->wasRequested() ? 128 + (int) $abort->signal() : 0);
