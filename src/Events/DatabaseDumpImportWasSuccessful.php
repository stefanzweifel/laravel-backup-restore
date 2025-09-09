<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Events;

class DatabaseDumpImportWasSuccessful
{
    public function __construct(public readonly string $absolutePathToDump) {}
}
