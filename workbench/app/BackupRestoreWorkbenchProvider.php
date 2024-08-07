<?php

declare(strict_types=1);

namespace Workbench\App;

use Illuminate\Support\ServiceProvider;
use ZipArchive;

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
                    'files' => [
                        'include' => [
                            base_path(),
                        ],
                        'exclude' => [
                            base_path('vendor'),
                            base_path('node_modules'),
                        ],
                        'follow_links' => false,
                        'ignore_unreadable_directories' => false,
                        'relative_path' => null,
                    ],
                    'databases' => [
                        'mysql',
                    ],
                ],
                'database_dump_compressor' => \Spatie\DbDumper\Compressors\GzipCompressor::class,
                'database_dump_file_timestamp_format' => null,
                'database_dump_filename_base' => 'database',
                'database_dump_file_extension' => '',

                'destination' => [
                    'compression_method' => ZipArchive::CM_DEFAULT,
                    'compression_level' => 9,
                    'filename_prefix' => '',
                    'disks' => [
                        'remote',
                    ],
                ],
                'temporary_directory' => storage_path('app/backup-temp'),
                'password' => env('BACKUP_ARCHIVE_PASSWORD'),
                'encryption' => 'default',
                'tries' => 1,
                'retry_delay' => 0,
            ],
            'notifications' => [
                'notifications' => [
                    \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
                    \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
                    \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
                    \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => ['mail'],
                    \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => ['mail'],
                    \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => ['mail'],
                ],
                'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,
                'mail' => [
                    'to' => 'your@example.com',
                    'from' => [
                        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                        'name' => env('MAIL_FROM_NAME', 'Example'),
                    ],
                ],
                'slack' => [
                    'webhook_url' => '',
                    'channel' => null,
                    'username' => null,
                    'icon' => null,
                ],
                'discord' => [
                    'webhook_url' => '',
                    'username' => '',
                    'avatar_url' => '',
                ],
            ],
            'monitor_backups' => [
                [
                    'name' => env('APP_NAME', 'laravel-backup'),
                    'disks' => ['local'],
                    'health_checks' => [
                        \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                        \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
                    ],
                ],
            ],
            'cleanup' => [
                'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,
                'default_strategy' => [
                    'keep_all_backups_for_days' => 7,
                    'keep_daily_backups_for_days' => 16,
                    'keep_weekly_backups_for_weeks' => 8,
                    'keep_monthly_backups_for_months' => 4,
                    'keep_yearly_backups_for_years' => 2,
                    'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
                ],
                'tries' => 1,
                'retry_delay' => 0,
            ],
        ]);
    }
}
