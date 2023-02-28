<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Wnx\LaravelBackupRestore\LaravelBackupRestoreServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Wnx\\LaravelBackupRestore\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelBackupRestoreServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_laravel-backup-restore_table.php.stub';
        $migration->up();
        */
    }

    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel_backup_restore'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
        ]);

        // Setup default filesystem disk where "remote" backups are stored
        $app['config']->set('filesystems.disks.remote', [
            'driver' => 'local',
            'root' => __DIR__.'/storage',
        ]);

        // Setup configuration for spatie/laravel-backup package that is relevant for this package
        $app['config']->set('backup', [
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

    public function setBackupSourceDatabase(string $connection = 'mysql')
    {
        // $app['config']->set('backup.backup.source.databases', ['mysql']);

        config()->set('backup.backup.source.databases', [$connection]);
        config()->set('backup.backup.database_dump_compressor', null);
        config()->set('backup.backup.database_dump_compressor', \Spatie\DbDumper\Compressors\GzipCompressor::class);

        // config()->set('backup.backup.password', null);
        // config()->set('backup.backup.encryption', 'default');
    }
}
