<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Wnx\LaravelBackupRestore\HealthChecks\Checks\DatabaseHasTables;
use Wnx\LaravelBackupRestore\HealthChecks\Result;

it('returns ok result by default', function () {
    $check = new DatabaseHasTables();
    $result = Result::make($check);

    expect($result)
        ->toBeInstanceOf(Result::class)
        ->status->toBe(Command::SUCCESS)
        ->message->toBe(null)
        ->healthCheck->toBe($check);
});

it('returns failed result when using failed method', function () {
    $check = new DatabaseHasTables();
    $result = Result::make($check);

    $result->failed('::message::');

    expect($result)
        ->toBeInstanceOf(Result::class)
        ->status->toBe(Command::FAILURE)
        ->message->toBe('::message::')
        ->healthCheck->toBe($check);
});

test('returns ok result when using ok method', function () {
    $check = new DatabaseHasTables();
    $result = Result::make($check);

    $result->failed('::message::');
    $result->ok();

    expect($result)
        ->toBeInstanceOf(Result::class)
        ->status->toBe(Command::SUCCESS)
        ->message->toBe(null)
        ->healthCheck->toBe($check);
});
