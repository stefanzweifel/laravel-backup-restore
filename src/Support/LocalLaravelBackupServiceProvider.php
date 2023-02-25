<?php

namespace Wnx\LaravelBackupRestore\Support;

use Illuminate\Support\ServiceProvider;

class LocalLaravelBackupServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Setup default filesystem disk where "remote" backups are stored
        $this->app['config']->set('filesystems.disks.remote', [
            'driver' => 'local',
            'root' => __DIR__ . '/../../tests/storage',
        ]);

        // Setup configuration for spatie/laravel-backup package that is relevant for this package
        $this->app['config']->set('backup', [
            'backup' => [
                'source' => [
                    'databases' => [
                        'mysql',
                    ],
                ],
                'database_dump_compressor' => \Spatie\DbDumper\Compressors\GzipCompressor::class,
                'database_dump_file_extension' => '',

                'destination' => [
                    'filename_prefix' => '',
                    'disks' => [
                        'remote',
                    ],
                ],
                'temporary_directory' => storage_path('app/backup-temp'),
                'password' => env('BACKUP_ARCHIVE_PASSWORD'),
                'encryption' => 'default',
            ],
        ]);
    }

    public function boot()
    {
        //
    }
}
