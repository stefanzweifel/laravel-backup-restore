# Security

## Reporting a vulnerability

Please email stefan@stefanzweifel.dev or [open a security advisory on
GitHub](https://github.com/stefanzweifel/laravel-backup-restore/security/advisories) instead of
opening a public issue.

## Before you restore

Restoring a backup runs the SQL inside it against your database. `php artisan backup:restore` is
therefore about as powerful as running a shell script from wherever the backup came from. It has
the privileges of the user running Artisan, plus whatever the database user on the restore
connection is allowed to do.

A few things follow from that.

### Whoever can write to your backup disk controls your next restore

If someone can put a file on the disk configured in `backup.backup.destination.disks`, they decide
what your next restore does. Passing `--password` doesn't help. ZIP encrypts each entry separately,
so an archive with no encrypted entries restores fine whether or not you supplied a password. A
password that "worked" doesn't prove the archive came from your backup job.

So:

- Limit `s3:PutObject` (or the equivalent) on the backup bucket to the backup job itself. The
  machine doing the restore only needs read access.
- Turn on object versioning, and object lock if it's available. That also helps when a backup gets
  overwritten rather than replaced.
- Don't restore a backup someone sent you on a machine you care about. Use a throwaway container.

### A dump can do more than recreate your tables

Nothing checks the SQL before it runs. A dump can add a database user and grant it privileges,
install a trigger or a `SECURITY DEFINER` function that fires the next time your app runs a query,
or simply insert rows: an admin account, a flipped feature flag, a new address on a `users` row
that then receives the next password reset.

You can't avoid this entirely, since importing a dump means running its statements, but you can
limit the damage:

- Restore as a database user that owns the application schema and nothing else. Not a superuser.
- On PostgreSQL a superuser connection also allows `COPY … FROM PROGRAM 'sh -c …'`, which runs
  commands on the database host. Worth keeping in mind for CI and local development, where restores
  often run as `postgres`.
- On MySQL, a connection with the `FILE` privilege and no `secure_file_priv` set allows
  `SELECT … INTO OUTFILE '/var/www/public/shell.php'`.
- The built-in `DatabaseHasTables` health check only counts tables, so it won't notice an injected
  admin user. If you restore from a disk you don't fully control, write a health check that looks
  at the data itself.

### `--reset` can't be undone

`--reset` drops every table on the target connection before importing. There's no transaction and
no snapshot, so if the dump turns out to be empty or truncated, the old data is gone. Take your own
snapshot first if the target holds anything you'd miss.

### Temporary files

While restoring, the package writes the downloaded archive and the **decrypted** SQL dump to
`storage/app/backup-restore-temp/`.

Make sure your webserver doesn't serve that directory. In a default Laravel app it sits outside
`public/`, so you're normally fine. Also remember that `--keep` leaves the decrypted dump on disk
on purpose — delete it once you're done with it.

### Credentials

Try not to pass `--password` on the command line. It ends up in your shell history, and while the
command runs it's visible in `ps` to anyone else on the machine. Set `backup.backup.password`
instead, or let the command prompt you.
