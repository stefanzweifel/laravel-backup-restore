<?php

namespace Wnx\LaravelBackupRestore;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Wnx\LaravelBackupRestore\Commands\LaravelBackupRestoreCommand;

class LaravelBackupRestoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-backup-restore')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel-backup-restore_table')
            ->hasCommand(LaravelBackupRestoreCommand::class);
    }
}
