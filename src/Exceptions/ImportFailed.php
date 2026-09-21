<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;
use Illuminate\Contracts\Process\ProcessResult;
use Throwable;

class ImportFailed extends Exception implements BackupRestoreException
{
    /**
     * Captured process output is truncated to this many bytes before it is
     * stored. A failing psql can emit megabytes.
     */
    protected const MAX_CAPTURED_OUTPUT_BYTES = 4096;

    protected const HINT_LINES = 20;

    protected function __construct(
        public readonly ?int $exitCode,
        public readonly ?string $output,
        public readonly ?string $errorOutput,
        public readonly ?string $dumpFile,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function processDidNotEndSuccessfully(ProcessResult $process, ?string $dumpFile = null): static
    {
        $exitCode = $process->exitCode();

        // The property keeps the full path; the message names the file only,
        // because the temp path is long and says nothing useful.
        $subject = $dumpFile === null
            ? 'The database import'
            : 'The import of "'.basename($dumpFile).'"';

        return new static(
            exitCode: $exitCode,
            output: static::truncate($process->output()),
            errorOutput: static::truncate($process->errorOutput()),
            dumpFile: $dumpFile,
            message: "{$subject} failed with exit code ".($exitCode ?? 'unknown').'.',
        );
    }

    /**
     * Wraps a failure from Wnx\LaravelBackupRestore\DbImporter so callers that
     * catch this exception keep working. The original is the previous
     * exception.
     */
    public static function fromImporter(Throwable $exception): self
    {
        return new self($exception->getMessage(), previous: $exception);
    }

    public static function decompressionFailed(string $filename, string $reason): static
    {
        return new static(
            exitCode: null,
            output: null,
            errorOutput: null,
            dumpFile: $filename,
            message: 'Could not decompress the dump file "'.basename($filename)."\": {$reason}.",
        );
    }

    public function hint(): ?string
    {
        if ($this->exitCode === null) {
            return 'Supported compression formats are gzip (.gz) and bzip2 (.bz2).';
        }

        $stream = $this->errorOutput !== null && trim($this->errorOutput) !== ''
            ? $this->errorOutput
            : $this->output;

        if ($stream === null || trim($stream) === '') {
            return 'The import command wrote nothing to stdout or stderr. Run the command with -v for the full context.';
        }

        $lines = explode("\n", trim($stream));

        return implode("\n", array_slice($lines, -static::HINT_LINES));
    }

    protected static function truncate(string $output): string
    {
        if (strlen($output) <= static::MAX_CAPTURED_OUTPUT_BYTES) {
            return $output;
        }

        return substr($output, -static::MAX_CAPTURED_OUTPUT_BYTES);
    }
}
