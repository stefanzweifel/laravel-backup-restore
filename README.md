# Restore database backups made with spatie/laravel-backup

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wnx/laravel-backup-restore.svg?style=flat-square)](https://packagist.org/packages/wnx/laravel-backup-restore)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/stefanzweifel/laravel-backup-restore/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/stefanzweifel/laravel-backup-restore/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/stefanzweifel/laravel-backup-restore/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/stefanzweifel/laravel-backup-restore/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/wnx/laravel-backup-restore.svg?style=flat-square)](https://packagist.org/packages/wnx/laravel-backup-restore)

A package to restore a database backup created by the [spatie/laravel-backup](https://github.com/spatie/laravel-backup) package.

The package requires Laravel v10.17 or higher and PHP 8.2 or higher.

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

#### `--only-db`
Restore only the database from the backup, ignoring other files in the backup. Defaults to `false`.

---

The command asks for confirmation before starting the restore process. If you run the `backup:restore`-command in an environment where you can't confirm the process (for example through a cronjob), you can use the `--no-interaction`-option to bypass the question.

```bash
php artisan backup:restore
    --disk=s3
    --backup=latest 
    --connection=mysql 
    --password=my-secret-password 
    --reset
    --no-interaction
```

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
            ->whereBetween('created_at', [
                now()->subDay()->startOfDay(), 
                now()->subDay()->endOfDay()
            ])
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

## Check Backup Integrity automatically with GitHub Actions
In addition to running the `backup:restore` command manually, you can also use this package to regularly test the integrity of your backups using GitHub Actions.

The GitHub Actions workflow below can either be triggered manually through the Github UI ([`workflow_dispatch`-trigger](https://docs.github.com/en/actions/using-workflows/events-that-trigger-workflows#workflow_dispatch)) or runs automatically on a schedule ([`schedule`-trigger](https://docs.github.com/en/actions/using-workflows/events-that-trigger-workflows#schedule)).
The workflow starts an empty MySQL database, clones your Laravel application, sets up PHP, installs composer dependencies and sets up the Laravel app. It then downloads, decrypts and restores the latest available backup to the MySQL database available in the GitHub Actions workflow run. The database is wiped, before the workflow completes.

Note that we pass a couple of env variables to the `backup:restore` command. Most of those values have been declared as [GitHub Action secrets](https://docs.github.com/en/actions/security-guides/encrypted-secrets). By using secrets our AWS keys are not being leaked in the workflow logs.

If the restore command fails, the entire workflow will fail, you and will receive a notification from GitHub.
This is obviously just a starting point. You can add more steps to the workflow, to – for example – notify you through Slack, if a restore succeeded or failed.

```yml
name: Validate Backup Integrity

on:
  # Allow triggering this workflow manually through the GitHub UI.
  workflow_dispatch:
  schedule:
    # Run workflow automatically on the first day of each month at 14:00 UTC
    # https://crontab.guru/#0_14_1_*_*
    - cron: "0 14 1 * *"

jobs:
  restore-backup:
    name: Restore backup
    runs-on: ubuntu-latest

    services:
      # Start MySQL and create an empty "laravel"-database
      mysql:
        image: mysql:latest
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: laravel
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2

      - uses: ramsey/composer-install@v2

      - run: cp .env.example .env

      - run: php artisan key:generate

      # Download latest backup and restore it to the "laravel"-database.
      # By default the command checks, if the database contains any tables after the restore. 
      # You can write your own Health Checks to extend this feature.
      - name: Restore Backup
        run: php artisan backup:restore --backup=latest --no-interaction
        env:
            APP_NAME: 'Laravel'
            DB_PASSWORD: 'password'
            AWS_ACCESS_KEY_ID: ${{ secrets.AWS_ACCESS_KEY_ID }}
            AWS_SECRET_ACCESS_KEY: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
            AWS_DEFAULT_REGION: ${{ secrets.AWS_DEFAULT_REGION }}
            AWS_BACKUP_BUCKET: ${{ secrets.AWS_BACKUP_BUCKET }}
            BACKUP_ARCHIVE_PASSWORD: ${{ secrets.BACKUP_ARCHIVE_PASSWORD }}

      # Wipe database after the backup has been restored.
      - name: Wipe Database
        run: php artisan db:wipe --no-interaction
        env:
            DB_PASSWORD: 'password'
```

## Testing

The package comes with an extensive test suite.
To run it, you need MySQL, PostgreSQL and sqlite installed on your system.

```bash
composer test
```

For MySQL and PostgreSQL the package expects that a `laravel_backup_restore` database exists and is accessible to a `root`-user without using a password.

You can change user, password and database by passing ENV-variables to the shell command tp run the tests … or change the settings locally to your needs. See [TestCase](https://github.com/stefanzweifel/laravel-backup-restore/blob/main/tests/TestCase.php) for details.

### Testing with Testbench

You can invoke the `backup:restore` command using `testbench` to test the command like you would in a Laravel application. 

```php
vendor/bin/testbench backup:restore --disk=remote
```

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
