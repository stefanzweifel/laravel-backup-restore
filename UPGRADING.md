# Upgrading

## Unreleased

### Database imports no longer go through a shell

Imports used to be built as a shell command string and run through `/bin/sh -c`. They are now
built as an argument array and handed to `Symfony\Component\Process\Process`, which passes the
arguments to the binary directly.

Nothing changes for you if you only run `php artisan backup:restore`.

### `gunzip`, `bunzip2` and `sqlite3` are no longer needed

Gzip and bzip2 dumps are decompressed in PHP through `compress.zlib://` and `compress.bzip2://`.
SQLite dumps are imported through PDO. The `gunzip`, `bunzip2` and `sqlite3` binaries are no
longer called.

`ext-zlib` is now a hard requirement, and `ext-bz2` is required to restore a bzip2-compressed
dump. Both are compiled in on most PHP builds.

### PostgreSQL restores fail on an error in the dump

`psql` is now run with `--set ON_ERROR_STOP=1`. Before, `psql` exited 0 after a statement failed
mid-dump and the restore was reported as successful. A restore that used to pass over a broken
statement now fails with a non-zero exit code.

### PostgreSQL dumps carrying psql meta-commands are refused

`psql` executes backslash meta-commands it reads from a dump, and `\!` runs a shell command. Dumps
are scanned and refused if they contain a meta-command that `pg_dump` does not itself emit
(`\.`, `\connect`, `\restrict` and `\unrestrict` are allowed).

Set `allow_psql_meta_commands` to `true` in `config/backup-restore.php` if you restore a dump that
legitimately needs one.

### `DbImporterFactory::extend()` takes a closure or a class name

```php
// Before — this never worked. forDriver() did `new $instance`, which is a fatal error.
DbImporterFactory::extend('sqlsrv', new SqlServerImporter);

// Now
DbImporterFactory::extend('sqlsrv', fn (array $config) => new SqlServerImporter($config));
DbImporterFactory::extend('sqlsrv', SqlServerImporter::class);
```

Passing an instance still works and is deprecated.

### `Wnx\LaravelBackupRestore\Databases\*` is deprecated

The import logic moved to `Wnx\LaravelBackupRestore\DbImporter\`, which has no Laravel
dependency. The old classes are kept and forward to it.

`getImportCommand(string $dumpFile, string $connection): string` keeps its signature but no longer
returns what is executed. It returns the argument vector joined with `escapeshellarg()`, for
display. It does not contain the dump file, which is streamed into the process on stdin, and for
SQLite it returns an empty string, because SQLite runs no command.

Use `DbImporterFactory::importerForConnection($connection)` to get the importer that actually runs,
and `getImportCommand(): array` on it.

### `import_binary_path` replaces `dump.dump_binary_path`

The path to the database clients moved into this package's own config:

```php
// config/backup-restore.php
'import_binary_path' => '/opt/homebrew/opt/mysql-client/bin',

// or one per connection
'import_binary_path' => [
    'mysql' => '/opt/homebrew/opt/mysql-client/bin',
    'pgsql' => '/Applications/Postgres.app/Contents/Versions/17/bin',
],
```

Until now the only way to point this package at a client was
`database.connections.*.dump.dump_binary_path`, which belongs to `spatie/laravel-backup` and means
the path to the *dump* binaries (`mysqldump`, `pg_dump`) rather than the import ones. That key is
still read when `import_binary_path` is empty, so nothing breaks, but it is deprecated and will
stop being read in the next major version.

### `CheckDependenciesAction` checks different binaries

It no longer checks for `gunzip`. It checks nothing at all for a SQLite connection, because
SQLite imports through PDO, and `Databases\Sqlite::getCliName()` returns an empty string for the
same reason — it used to return `'gunzip'`. For MySQL, MariaDB and PostgreSQL it checks the binary
at the connection's `dump.dump_binary_path`, which is the path the importer actually calls,
rather than the bare name on `PATH`.

The action is still not called: `RestoreCommand::handle()` has the call commented out, as it was
before. `dump.dump_binary_path` is `spatie/laravel-backup`'s path to the *dump* binaries rather
than the import ones, so turning the check on would make a slightly wrong path a hard failure
before the restore even starts. A custom-format PostgreSQL dump also runs `pg_restore` instead of
`psql`, and which of the two it will be is only known once the dump is on disk and its magic
number has been read.

### A `mariadb` connection now works

`spatie/laravel-backup` supports the `mariadb` driver; this package did not. A connection with
`'driver' => 'mariadb'` is now imported with the `mariadb` binary.
