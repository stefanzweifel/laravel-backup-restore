<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Exceptions;

use RuntimeException;
use Symfony\Component\Process\Process;

class ImportFailed extends RuntimeException
{
    /**
     * A failing import can write megabytes to stderr. Keep the tail, which is
     * where the error that stopped the import is.
     */
    public const int MAX_OUTPUT_LENGTH = 10240;

    /**
     * How much of a failing statement the message quotes. A single INSERT can
     * be megabytes wide.
     */
    public const int MAX_STATEMENT_LENGTH = 200;

    public function __construct(
        string $message,
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $errorOutput,
    ) {
        parent::__construct($message);
    }

    public static function processDidNotEndSuccessfully(Process $process): self
    {
        $exitCode = $process->getExitCode() ?? -1;
        $output = static::truncate($process->getOutput());
        $errorOutput = static::truncate($process->getErrorOutput());

        $reason = $errorOutput !== '' ? str_replace("\n", ' ', trim($errorOutput)) : 'no error output';

        return new self(
            "The import process exited with code {$exitCode}: {$reason}",
            $exitCode,
            $output,
            $errorOutput,
        );
    }

    public static function timedOut(int $timeout): self
    {
        return new self("The import did not finish within {$timeout} seconds.", -1, '', '');
    }

    public static function statementFailed(string $message): self
    {
        return new self("The import failed: {$message}", 1, '', $message);
    }

    /**
     * A single statement was rejected. The statement is named and quoted so
     * that the dump can be looked at, but a row of a few megabytes must not
     * end up in the message.
     */
    public static function statementInDumpFailed(int $number, string $statement, string $message): self
    {
        $excerpt = str_replace("\n", ' ', trim(static::shorten($statement)));

        return new self(
            "The import failed at statement {$number} ({$excerpt}): {$message}",
            1,
            '',
            $message,
        );
    }

    protected static function shorten(string $statement): string
    {
        if (strlen($statement) <= static::MAX_STATEMENT_LENGTH) {
            return $statement;
        }

        return substr($statement, 0, static::MAX_STATEMENT_LENGTH).' … (truncated)';
    }

    protected static function truncate(string $output): string
    {
        if (strlen($output) <= static::MAX_OUTPUT_LENGTH) {
            return $output;
        }

        return '… (truncated) '.substr($output, -static::MAX_OUTPUT_LENGTH);
    }
}
