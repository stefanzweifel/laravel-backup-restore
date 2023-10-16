<?php

declare(strict_types=1);

namespace Workbench\App;

use Illuminate\Support\ServiceProvider;

class BackupRestoreWorkbenchProvider extends ServiceProvider
{
    public function boot()
    {
        // Setup default database to use sqlite :memory:
        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => 'database/database.sqlite',
        ]);
        $this->app['config']->set('database.connections.sqlite-restore', [
            'driver' => 'sqlite',
            'database' => 'database/database.sqlite',
        ]);

        $this->app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('MYSQL_HOST', '127.0.0.1'),
            'port' => env('MYSQL_PORT', '3306'),
            'database' => env('MYSQL_DATABASE', 'laravel_backup_restore'),
            'username' => env('MYSQL_USERNAME', 'root'),
            'password' => env('MYSQL_PASSWORD', ''),
        ]);

        $this->app['config']->set('database.connections.mysql-restore', [
            'driver' => 'mysql',
            'host' => env('MYSQL_HOST', '127.0.0.1'),
            'port' => env('MYSQL_PORT', '3306'),
            'database' => env('MYSQL_DATABASE', 'laravel_backup_restore'),
            'username' => env('MYSQL_USERNAME', 'root'),
            'password' => env('MYSQL_PASSWORD', ''),
        ]);

        $this->app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('PGSQL_HOST', '127.0.0.1'),
            'port' => env('PGSQL_PORT', '5432'),
            'database' => env('PGSQL_DATABASE', 'laravel_backup_restore'),
            'username' => env('PGSQL_USERNAME', 'root'),
            'password' => env('PGSQL_PASSWORD', ''),
            'search_path' => 'public',
        ]);
        $this->app['config']->set('database.connections.pgsql-restore', [
            'driver' => 'pgsql',
            'host' => env('PGSQL_HOST', '127.0.0.1'),
            'port' => env('PGSQL_PORT', '5432'),
            'database' => env('PGSQL_DATABASE', 'laravel_backup_restore'),
            'username' => env('PGSQL_USERNAME', 'root'),
            'password' => env('PGSQL_PASSWORD', ''),
            'search_path' => 'public',
        ]);

        $this->app['config']->set('database.connections.unsupported-driver', [
            'driver' => 'sqlsrv',
        ]);

        // Setup default filesystem disk where "remote" backups are stored
        $this->app['config']->set('filesystems.disks.remote', [
            'driver' => 'local',
            'root' => __DIR__.'/../../tests/storage',
        ]);

        // Setup configuration for spatie/laravel-backup package that is relevant for this package
        $this->app['config']->set('backup', [
            'backup' => [
                'name' => env('APP_NAME', 'Laravel'),
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
}
