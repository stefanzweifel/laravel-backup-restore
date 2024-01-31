<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;
use Illuminate\Contracts\Process\ProcessResult;

class ImportFailed extends Exception
{
    public static function processDidNotEndSuccessfully(ProcessResult $process): static
    {
        $processOutput = static::formatProcessOutput($process);

        return new static("The import process failed with a none successful exitcode.{$processOutput}");
    }

    public static function decompressionFailed(string $filename, string $reason): static
    {
        return new static("Could not decompress $filename dump file: $reason");
    }

    protected static function formatProcessOutput(ProcessResult $process): string
    {
        $output = $process->output() ?: '<no output>';
        $errorOutput = $process->errorOutput() ?: '<no output>';
        $exitCodeText = $process->exitCode() ?: '<no exit text>';

        return <<<CONSOLE

            Exitcode
            ========
            {$process->exitCode()}: {$exitCodeText}

            Output
            ======
            {$output}

            Error Output
            ============
            {$errorOutput}
            CONSOLE;
    }
}
