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

    public static function statementFailed(string $message): self
    {
        return new self("The import failed: {$message}", 1, '', $message);
    }

    protected static function truncate(string $output): string
    {
        if (strlen($output) <= static::MAX_OUTPUT_LENGTH) {
            return $output;
        }

        return '… (truncated) '.substr($output, -static::MAX_OUTPUT_LENGTH);
    }
}
