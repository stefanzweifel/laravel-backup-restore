# A package to restore database backups made with spatie/laravel-backup.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wnx/laravel-backup-restore.svg?style=flat-square)](https://packagist.org/packages/wnx/laravel-backup-restore)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/stefanzweifel/laravel-backup-restore/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/stefanzweifel/laravel-backup-restore/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/stefanzweifel/laravel-backup-restore/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/stefanzweifel/laravel-backup-restore/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/wnx/laravel-backup-restore.svg?style=flat-square)](https://packagist.org/packages/wnx/laravel-backup-restore)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.
## Installation

You can install the package via composer:

```bash
composer require wnx/laravel-backup-restore
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-backup-restore-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-backup-restore-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-backup-restore-views"
```

## Usage

```php
$laravelBackupRestore = new Wnx\LaravelBackupRestore();
echo $laravelBackupRestore->echoPhrase('Hello, Wnx!');
```

## Testing

```bash
composer test
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
