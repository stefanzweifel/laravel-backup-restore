<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Wnx\LaravelBackupRestore\Commands\RestoreCommand;

class LaravelBackupRestoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-backup-restore')
            ->hasConfigFile()
            ->hasCommand(RestoreCommand::class);
    }
}
