<?php

declare(strict_types=1);

use Wnx\LaravelBackupRestore\RestoreAbort;

it('starts out with no abort requested', function () {
    $abort = new RestoreAbort;

    expect($abort->wasRequested())->toBeFalse();
    expect($abort->signal())->toBeNull();
});

it('records the signal it was aborted with', function () {
    $abort = new RestoreAbort;

    $abort->requestAbort(15);

    expect($abort->wasRequested())->toBeTrue();
    expect($abort->signal())->toBe(15);
});

it('keeps the first signal when a second one arrives', function () {
    $abort = new RestoreAbort;

    $abort->requestAbort(2);
    $abort->requestAbort(15);

    expect($abort->signal())->toBe(2);
});

it('runs every registered stopper', function () {
    $abort = new RestoreAbort;
    $ran = [];

    $abort->whileRunning(function () use (&$ran) {
        $ran[] = 'first';
    });
    $abort->whileRunning(function () use (&$ran) {
        $ran[] = 'second';
    });

    $abort->requestAbort(15);

    expect($ran)->toBe(['first', 'second']);
});

it('runs a stopper only once when two signals arrive', function () {
    $abort = new RestoreAbort;
    $calls = 0;

    $abort->whileRunning(function () use (&$calls) {
        $calls++;
    });

    $abort->requestAbort(2);
    $abort->requestAbort(15);

    expect($calls)->toBe(1);
});

it('does not run a stopper that was de-registered', function () {
    $abort = new RestoreAbort;
    $ran = false;

    $release = $abort->whileRunning(function () use (&$ran) {
        $ran = true;
    });

    $release();
    $abort->requestAbort(15);

    expect($ran)->toBeFalse();
});

it('runs the remaining stoppers when one of them throws', function () {
    $abort = new RestoreAbort;
    $ran = false;

    $abort->whileRunning(fn () => throw new RuntimeException('process already gone'));
    $abort->whileRunning(function () use (&$ran) {
        $ran = true;
    });

    $abort->requestAbort(15);

    expect($ran)->toBeTrue();
    expect($abort->wasRequested())->toBeTrue();
});

it('runs a stopper registered after the abort was requested', function () {
    $abort = new RestoreAbort;
    $ran = false;

    $abort->requestAbort(15);
    $abort->whileRunning(function () use (&$ran) {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});

it('retries a stopper that threw when a second signal arrives', function () {
    $abort = new RestoreAbort;
    $calls = 0;

    $abort->whileRunning(function () use (&$calls) {
        $calls++;

        if ($calls === 1) {
            throw new RuntimeException('cannot signal the child from here');
        }
    });

    $abort->requestAbort(2);
    expect($calls)->toBe(1);

    $abort->requestAbort(15);
    expect($calls)->toBe(2);

    // Succeeded the second time, so it is not registered any more.
    $abort->requestAbort(15);
    expect($calls)->toBe(2);
});
