<?php

namespace Wnx\LaravelBackupRestore\Exceptions;

use Symfony\Component\Process\Process;

class ImportFailed extends \Exception
{
    public static function processDidNotEndSuccessfully(Process $process): static
    {
        $processOutput = static::formatProcessOutput($process);

        return new static("The import process failed with a none successful exitcode.{$processOutput}");
    }

    protected static function formatProcessOutput(Process $process): string
    {
        $output = $process->getOutput() ?: '<no output>';
        $errorOutput = $process->getErrorOutput() ?: '<no output>';
        $exitCodeText = $process->getExitCodeText() ?: '<no exit text>';

        return <<<CONSOLE

            Exitcode
            ========
            {$process->getExitCode()}: {$exitCodeText}

            Output
            ======
            {$output}

            Error Output
            ============
            {$errorOutput}
            CONSOLE;
    }
}
