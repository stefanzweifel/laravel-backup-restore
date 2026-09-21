<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\DbImporter\Databases;

/**
 * MariaDB 10.5 and later ship the client as `mariadb`; `mysql` is a symlink
 * that later releases drop. spatie/db-dumper's MariaDb uses `mariadb-dump`
 * unconditionally, so this uses `mariadb` unconditionally to match. Point
 * setImportBinaryPath() at the directory holding the binary if it is not on
 * PATH under that name.
 */
class MariaDb extends MySql
{
    public function getBinaryName(): string
    {
        return 'mariadb';
    }
}
