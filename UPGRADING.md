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

### A `mariadb` connection now works

`spatie/laravel-backup` supports the `mariadb` driver; this package did not. A connection with
`'driver' => 'mariadb'` is now imported with the `mariadb` binary.
