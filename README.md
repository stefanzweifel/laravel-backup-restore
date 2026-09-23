# Restore database backups made with spatie/laravel-backup

[![Latest Version on Packagist](https://img.shields.io/packagist/v/wnx/laravel-backup-restore.svg?style=flat-square)](https://packagist.org/packages/wnx/laravel-backup-restore)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/stefanzweifel/laravel-backup-restore/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/stefanzweifel/laravel-backup-restore/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/stefanzweifel/laravel-backup-restore/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/stefanzweifel/laravel-backup-restore/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/wnx/laravel-backup-restore.svg?style=flat-square)](https://packagist.org/packages/wnx/laravel-backup-restore)

A package to restore a database backup created by the [spatie/laravel-backup](https://github.com/spatie/laravel-backup) package.

The package requires Laravel v12 or higher and PHP 8.4 or higher.

MySQL and MariaDB restores need the `mysql` or `mariadb` client on the machine running the command,
PostgreSQL restores need `psql` and `pg_restore`. SQLite restores need neither: they go through
PDO. Compressed dumps are decompressed in PHP, so `gunzip` and `bunzip2` are not needed
(`ext-zlib` is required, `ext-bz2` for bzip2 dumps).

## Installation

You can install the package via composer:

```bash
composer require wnx/laravel-backup-restore
```

Optionally, you can publish the config file with:

```bash
php artisan vendor:publish --tag="backup-restore-config"
```

These are the contents of the published config file:

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

    /**
     * psql executes backslash meta-commands it reads from a dump, and `\!` runs
     * a shell command. Dumps are therefore scanned and refused if they contain
     * a meta-command that pg_dump does not itself emit.
     *
     * Set this to true to hand meta-commands to psql anyway. Only do that for
     * dumps you trust.
     */
    'allow_psql_meta_commands' => false,

    /**
     * The directory holding the database clients used to import a dump: mysql,
     * mariadb, psql and pg_restore. Leave this empty when they are on the PATH
     * of the PHP process, which is the usual case.
     *
     * Set one path for every connection:
     *
     *     'import_binary_path' => '/opt/homebrew/opt/mysql-client/bin',
     *
     * or one per connection, when the clients live in different places:
     *
     *     'import_binary_path' => [
     *         'mysql' => '/opt/homebrew/opt/mysql-client/bin',
     *         'pgsql' => '/Applications/Postgres.app/Contents/Versions/17/bin',
     *     ],
     *
     * When this is empty, the connection's dump.dump_binary_path in
     * config/database.php is used instead. That key belongs to
     * spatie/laravel-backup and points at the dump binaries rather than the
     * import ones; reading it is deprecated and will stop in the next major
     * version.
     */
    'import_binary_path' => env('BACKUP_RESTORE_IMPORT_BINARY_PATH', ''),
];
```

## Security

Restoring a backup from a disk you do not fully control is close to running a shell script from
that disk. A database dump is a script the database client executes, and the clients do more than
run SQL:

- `psql` runs a line starting with `\!` as a shell command, whether or not stdin is a terminal.
  There is no flag that turns that off. This package scans a plain-SQL PostgreSQL dump before
  handing it to `psql` and refuses any meta-command that `pg_dump` does not itself emit. The
  `allow_psql_meta_commands` config option turns the scan off for a dump you trust.
- `sqlite3` does the same for `.shell` and `.system`. This package imports SQLite dumps through
  PDO, which has no such commands.
- The `mysql` client rejects `system` and `\!` from redirected stdin, so MySQL and MariaDB need
  nothing extra.

Dumps also contain ordinary SQL, which can drop tables, create users and read files the database
server can read. Restore only from a disk you trust.

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
    --keep
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

Dropping the tables cannot be undone, so the command checks the database dumps in the backup first. If a dump is empty, or holds no `CREATE TABLE`, `INSERT INTO` or `COPY ... FROM stdin` statement, the restore stops before any table is dropped. Dumps larger than 1 MB and dumps in a format that cannot be read as text (a binary `pg_dump`, for example) are not checked.

#### `--keep`
Keeps the downloaded backup (and the decrypted backup folder) in existence. You need to delete it by hand. Useful for extracting and restoring backuped files. Defaults to `false`.

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

### Exit Codes

| Code | Meaning |
|---|---|
| `0` | The backup was restored and all health checks passed. |
| `1` | The restore failed, or a health check failed after the import. |
| `2` | The restore was not confirmed at the prompt. |

### Handling Failures

Every exception the package throws implements `Wnx\LaravelBackupRestore\Exceptions\BackupRestoreException`, so one catch block covers all of them.

```php
use Wnx\LaravelBackupRestore\Exceptions\BackupRestoreException;

try {
    Artisan::call('backup:restore', ['--disk' => 's3', '--backup' => 'latest']);
} catch (BackupRestoreException $e) {
    report($e->getMessage());
    report($e->hint());
}
```

`getMessage()` is a single line naming what went wrong. `hint()` returns the next step to take, or `null`.

Each exception also carries the relevant facts as readonly properties, so you do not have to parse the message:

| Exception | Thrown when | Properties |
|---|---|---|
| `NoBackupsFound` | The disk holds no `.zip` files | `disk`, `backupName` |
| `DecompressionFailed` | The archive cannot be opened or extracted | `archive`, `errorCode`, `entryName` |
| `NoDatabaseDumpsFound` | The archive has no `db-dumps` to import | `backup`, `filesInBackup` |
| `DumpIsNotRestorable` | A dump is empty or holds no statements | `dumpFile`, `reason` |
| `CannotCreateDbImporter` | The connection is missing or its driver is unsupported | `connectionName`, `driver` |
| `CliNotFound` | The database binary is not on the `PATH` | `cli` |
| `ImportFailed` | The import command exited non-zero | `exitCode`, `output`, `errorOutput`, `dumpFile` |
| `InvalidHealthCheck` | A configured health check is not a `HealthCheck` | `healthCheck`, `configKey` |

`ImportFailed` keeps the last 4 KB of each captured stream. The `backup:restore`-command prints the message and the hint; run it with `-v` to also get the exception class, the stack trace and the full captured stderr.

## Limitations

- The package only supports backups created by the [spatie/laravel-backup](https://github.com/spatie/laravel-backup) package.
- The package does not support restoring files from backups.
- The package does not support restoring backups from or in a multi-tenant environment.

## Troubleshooting

### Failed to restore Backup: @@GLOBAL.GTID_PURGED cannot be changed

If your MySQL backup was created on a DigitalOcean managed database, you might encounter the following error when restoring the backup:

> ERROR 3546 (HY000) at line 14: @@GLOBAL.GTID_PURGED cannot be changed: the added gtid set must not overlap with @@GLOBAL.GTID_EXECUTED

This is caused by DigitalOcean's MySQL settings that enable [GTID](https://dev.mysql.com/doc/refman/8.4/en/replication-gtids-concepts.html) on their managed databases.

You can disable GTID when creating the MySQL backup by adding the following option to the `dump`-section of your database connection in `config/backup.php`-file:

```php
'dump' => [
    'add_extra_option' => '--set-gtid-purged=OFF --skip-disable-keys',
],
```

If your backup was created with GTID enabled, the package currently can't restore it.   
See [Issue 93](https://github.com/stefanzweifel/laravel-backup-restore/issues/93) for details.

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

For MySQL: `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_USERNAME`, `MYSQL_PASSWORD`, `MYSQL_DATABASE`.
For PostgreSQL: `PGSQL_HOST`, `PGSQL_PORT`, `PGSQL_USERNAME`, `PGSQL_PASSWORD`, `PGSQL_DATABASE`.

The test suite runs on Linux, macOS and Windows.

The MySQL and PostgreSQL tests need the `mysql` and `psql` clients on `PATH`.
Linux and macOS install both with the database itself; on Windows they come with
the MySQL and PostgreSQL installers. The SQLite tests and every compressed
fixture run without an external binary. See the `run-tests` workflow for how CI
sets this up.

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
