<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Exceptions;

use RuntimeException;

class DumpContainsMetaCommand extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $lineNumber,
        public readonly string $metaCommand,
    ) {
        parent::__construct($message);
    }

    public static function found(int $lineNumber, string $metaCommand): self
    {
        return new self(
            "The dump contains the psql meta-command `{$metaCommand}` on line {$lineNumber}. ".
            'psql runs meta-commands such as `\!` as shell commands, so the dump is refused. '.
            'Set `backup-restore.allow_psql_meta_commands` to true to import it anyway.',
            $lineNumber,
            $metaCommand,
        );
    }
}
