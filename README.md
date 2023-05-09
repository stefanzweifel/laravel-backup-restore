# Restore database backups made with spatie/laravel-backup

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wnx/laravel-backup-restore.svg?style=flat-square)](https://packagist.org/packages/wnx/laravel-backup-restore)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/stefanzweifel/laravel-backup-restore/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/stefanzweifel/laravel-backup-restore/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/stefanzweifel/laravel-backup-restore/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/stefanzweifel/laravel-backup-restore/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/wnx/laravel-backup-restore.svg?style=flat-square)](https://packagist.org/packages/wnx/laravel-backup-restore)

A package to restore a database backup created by the [spatie/laravel-backup](https://github.com/spatie/laravel-backup) package.

> **Note**   
> Although no v1 of this package has been tagged yet, it has been thoroughly tested and proven to work well.   
> We are still working on implementing a "health check" to ensure that the database has been imported correctly after a restore. We will tag a v1 once this feature has finished.
> Thank you for using our package!

## Installation

You can install the package via composer:

```bash
composer require wnx/laravel-backup-restore
```

## Usage

To restore a backup, run the following command.

```bash
php artisan backup:restore
```

You will be prompted to select the backup you want to restore and whether the encryption password from the configuration should be used, to decrypt the backup.

The package relies on an existing `config/backup.php`-file to find your backups, encryption/decryption key and database connections.

> **Note**   
> By default, the name of a backup equals the value of the APP_NAME-env variable. The restore looks for backups in a folder with the backup name. Make sure that the APP_NAME-value is correct in the environment you're running the command. 

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
