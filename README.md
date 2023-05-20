# Restore database backups made with spatie/laravel-backup

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wnx/laravel-backup-restore.svg?style=flat-square)](https://packagist.org/packages/wnx/laravel-backup-restore)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/stefanzweifel/laravel-backup-restore/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/stefanzweifel/laravel-backup-restore/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/stefanzweifel/laravel-backup-restore/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/stefanzweifel/laravel-backup-restore/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/wnx/laravel-backup-restore.svg?style=flat-square)](https://packagist.org/packages/wnx/laravel-backup-restore)

A package to restore a database backup created by the [spatie/laravel-backup](https://github.com/spatie/laravel-backup) package.

## Installation

You can install the package via composer:

```bash
composer require wnx/laravel-backup-restore
```

Optionally, you can publish the config file with:

```bash
php artisan vendor:publish --tag="backup-restore-config"
```

This is the contents of the published config file:

```php
return [

    /**
     * Health checks are run after a given backup has been restored.
     * With health checks, you can make sure that the restored database contains the data you expect.
     * By default, we check if the restored database contains any tables.
     *
     * You can add your own health checks by adding a class that extends the HealthCheck class.
     * The restore command will fail, if any health checks fail.
     */
    'health-checks' => [
        \Wnx\LaravelBackupRestore\HealthChecks\Checks\DatabaseHasTables::class,
    ],
];
```

## Usage

To restore a backup, run the following command.

```bash
php artisan backup:restore
```

You will be prompted to select the backup you want to restore and whether the encryption password from the configuration should be used, to decrypt the backup.

The package relies on an existing `config/backup.php`-file to find your backups, encryption/decryption key and database connections.

> **Note**   
> By default, the name of a backup equals the value of the APP_NAME-env variable. The restore-commands looks for backups in a folder with that backup name. Make sure that the APP_NAME-value is correct in the environment you're running the command. 

### Optional Command Options

You can pass disk, backup, database connection and decryption password to the Artisan command directly, to speed things up.

```bash
php artisan backup:restore
    --disk=s3
    --backup=latest 
    --connection=mysql 
    --password=my-secret-password 
    --reset
```

Note that we used `latest` as the value for `--backup`. The command will automatically download the latest available backup and restore its database.

#### `--disk`
The filesystem disk to look for backups. Defaults to the first destination disk configured in `config/backup.php`.

#### `--backup`
Relative path to the backup file that should be restored.
Use `latest` to automatically select latest backup.

#### `--connection`
Database connection to restore backup. Defaults to the first source database connection configured in `config/backup.php`.

#### `--password`
Password used to decrypt a possible encrypted backup. Defaults to encryption password set in `config/backup.php`.

#### `--reset`
Reset the database before restoring the backup. Defaults to `false`.

### Health Checks
After the backup has been restored, the package will run a series of health checks to ensure that the database has been imported correctly.
By default, the package will check if the database has tables after the restore.

You can add your own health checks by creating classes that extend `Wnx\LaravelBackupRestore\HealthChecks\HealthCheck`-class.

```php
namespace App\HealthChecks;

use Wnx\LaravelBackupRestore\PendingRestore;
use Wnx\LaravelBackupRestore\HealthChecks\HealthCheck;

class MyCustomHealthCheck extends HealthCheck
{
    public function run(PendingRestore $pendingRestore): Result
    {
        $result = Result::make($this);

        // We assume that your app generates sales every day.
        // This check ensures that the database contains sales from yesterday.
        $newSales = \App\Models\Sale::query()
            ->whereBetween('created_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])
            ->exists();

        // If no sales were created yesterday, we consider the restore as failed.
        if ($newSales === false) {
            return $result->failed('Database contains no sales from yesterday.');
        }

        return $result->ok();
    }
}
```

Add your health check to the `health-checks`-array in the `config/laravel-backup-restore.php`-file.

```php
    'health-checks' => [
        \Wnx\LaravelBackupRestore\HealthChecks\Checks\DatabaseHasTables::class,
        \App\HealthChecks\MyCustomHealthCheck::class,
    ],
```

## Testing

The package comes with an extensive test suite.
To run it, you need MySQL, PostgreSQL and sqlite installed on your system.

```bash
composer test
```

For MySQL and PostgreSQL the package expects that a `laravel_backup_restore` database exists and is accessible to a `root`-user without using a password.

You can change user, password and database by passing ENV-variables to the shell command tp run the tests … or change the settings locally to your needs. See [TestCase](https://github.com/stefanzweifel/laravel-backup-restore/blob/main/tests/TestCase.php) for details.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Stefan Zweifel](https://github.com/stefanzweifel)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
