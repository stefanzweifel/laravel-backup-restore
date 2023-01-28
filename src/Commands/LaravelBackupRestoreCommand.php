<?php

namespace Wnx\LaravelBackupRestore\Commands;

use Illuminate\Console\Command;

class LaravelBackupRestoreCommand extends Command
{
    public $signature = 'laravel-backup-restore';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
