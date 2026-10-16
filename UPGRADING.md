# Upgrading

## From v1 to v2

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

### The database client is checked before the backup is downloaded

`backup:restore` looks for the database client it will need before it downloads anything, and
stops with a `CliNotFound` if it is missing. `CheckDependenciesAction` has been in the package for
a long time but was never called; it is now wired into `RestoreCommand`.

A restore that used to download a backup, extract it and then fail on a missing `mysql` or `psql`
now fails immediately instead. If the clients are somewhere other than the `PATH` of the PHP
process, set `import_binary_path` (see below). SQLite connections are not checked, because they
are imported through PDO.

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
at the configured `import_binary_path`, which is the path the importer actually calls, rather than
the bare name on `PATH`.

A PostgreSQL connection is checked for `pg_restore` as well as `psql`. A custom-format dump is
restored with `pg_restore`, and which of the two it will be is only known once the dump has been
downloaded — after the check has run — so both have to be present.

A configured path is checked against the filesystem rather than looked up with `which` or `where`,
which only search the `PATH` the binary is not on.

### A `mariadb` connection now works

`spatie/laravel-backup` supports the `mariadb` driver; this package did not. A connection with
`'driver' => 'mariadb'` is now imported with the `mariadb` binary.

### A failed import keeps its temporary files

A restore that fails or is interrupted once `--reset` or the import has started now leaves the
downloaded archive and the extracted dump in `storage/app/backup-restore-temp` (by default — the
path comes from `filesystems.disks.local.root`). Earlier versions deleted them on every failure.
The database may hold a partial restore at that point, and the files are kept so you can look at
the dump or import it by hand. Re-running the command ignores them and downloads the backup again.
Delete the directory by hand once you no longer need it — the dump is plaintext.

The files are still deleted when:

- the restore fails before `--reset` or the import has started (for example on a missing client,
  an unreadable archive or a dump that fails the pre-reset check),
- the import finished, even if a health check then fails.

`--keep` is unchanged.

### `backup:restore` handles `SIGINT`, `SIGTERM` and `SIGHUP`

The running import is stopped, the temporary files are handled as described above, and a
`Wnx\LaravelBackupRestore\Events\RestoreAborted` event is dispatched. The command exits with
`128 + signal`: `129` for `SIGHUP`, `130` for `SIGINT` and `143` for `SIGTERM`. A second signal ends
the process immediately.

This needs `ext-pcntl`. Without it (Windows, or a PHP build without the extension) nothing is
registered and a signal kills the process as before. `SIGINT` is not handled while a spinner is
running, because `laravel/prompts` installs its own handler for it.

### `backup:restore` no longer throws package exceptions

Exceptions that implement `Wnx\LaravelBackupRestore\Exceptions\BackupRestoreException` (all of the
package's own, such as `ImportFailed`, `NoBackupsFound` and `CliNotFound`) are caught by the
command. It prints the message and a hint and returns exit code `1`. Code that wraps
`Artisan::call('backup:restore')` in a `try`/`catch` will no longer see them; check the return
value instead.

The exceptions changed as well:

- Constructors are `protected` and the named constructors return `self` instead of `static`.
- Messages were reworded. `ImportFailed` no longer puts the process output in the message; read
  `->exitCode`, `->output` and `->errorOutput` instead.
- Every exception has a `hint()` method.

### `--reset` checks the dumps before it drops tables

With `--reset`, each dump is checked first. An empty dump, or one with no `CREATE TABLE`,
`INSERT INTO` or `COPY … FROM stdin` statement in its first megabyte, stops the restore with
`DumpIsNotRestorable` before anything is dropped. Binary `pg_dump` archives and dumps larger than
1 MB are not inspected.

### The archive is validated and written with tighter permissions

`DecompressBackupAction` now:

- rejects zip entries with absolute paths, null bytes or `..` segments that leave the extraction
  directory (`DecompressionFailed`),
- throws if you supply a password but the archive's entries are not AES-encrypted, instead of
  restoring an archive that is not the expected one,
- writes the extracted dump with mode `0600`, and the downloaded archive is stored as private.

### New requirements

- `ext-pdo_sqlite` is needed to restore a SQLite dump. It is listed under `suggest`.
- `ext-pcntl` is optional and enables signal handling.
- `spatie/temporary-directory` is no longer a dependency of this package. Require it yourself if
  your application relies on it being installed.

### `DecompressBackupAction::execute()` and `ImportDumpAction::execute()` take a second parameter

Both now take `?RestoreAbort $abort = null`, which is how an interrupted restore stops part-way
through:

```php
// Before
public function execute(PendingRestore $pendingRestore): void

// Now
public function execute(PendingRestore $pendingRestore, ?RestoreAbort $abort = null): void
```

A subclass that overrides `execute()` with the old single-parameter signature is a fatal error: PHP
requires an override to accept at least the parameters of the method it replaces. Add the parameter
to your override; you can ignore it.

The importers were deliberately left alone for the same reason — `getImportCommand()`,
`importFromFile()` and `importToDatabase()` keep their signatures. They carry the token as a
property, set through `abortWith(?RestoreAbort $abort)`.

### Smaller API changes

- `PendingRestore::make()` takes named, typed parameters (`disk`, `backup`, `connection`,
  `backupPassword`, `restoreDisk`) instead of `...$attributes`.
- `ResetDatabaseAction::execute()` has a `void` return type.
- Paths inside the restore disk always use `/`, also on Windows.
