<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Process;
use Wnx\LaravelBackupRestore\DbImporterFactory;
use Wnx\LaravelBackupRestore\Exceptions\CannotCreateDbImporter;
use Wnx\LaravelBackupRestore\Exceptions\CliNotFound;

class CheckDependenciesAction
{
    /**
     * @throws CannotCreateDbImporter
     * @throws CliNotFound
     * @throws \Throwable
     */
    public function execute(string $connection): void
    {
        foreach ($this->binariesFor($connection) as $binary) {
            $this->checkIfCliExists($binary);
        }
    }

    /**
     * @return array<int, string>
     *
     * @throws CannotCreateDbImporter
     */
    protected function binariesFor(string $connection): array
    {
        $databaseCli = DbImporterFactory::createFromConnection($connection)->getCliName();

        // SQLite imports through PDO and needs no binary at all.
        if ($databaseCli === '') {
            return [];
        }

        $binaries = [$databaseCli];

        // A custom-format dump is restored with pg_restore rather than psql.
        // Which of the two it will be is only known once the dump has been
        // downloaded, so both have to be there.
        if ($databaseCli === 'psql') {
            $binaries[] = 'pg_restore';
        }

        $path = DbImporterFactory::binaryPathForConnection($connection);

        if ($path !== '' && ! str_ends_with($path, '/') && ! str_ends_with($path, DIRECTORY_SEPARATOR)) {
            $path .= DIRECTORY_SEPARATOR;
        }

        return array_map(fn (string $binary): string => $path.$binary, $binaries);
    }

    /**
     * @throws CliNotFound|\Throwable
     */
    protected function checkIfCliExists(string $cli): void
    {
        throw_if(! $this->canBeExecuted($cli), CliNotFound::create($cli));
    }

    protected function canBeExecuted(string $cli): bool
    {
        // A configured path names one file, so ask the filesystem about that
        // file rather than searching the PATH it is not on.
        if (str_contains($cli, '/') || str_contains($cli, DIRECTORY_SEPARATOR)) {
            return $this->isExecutableFile($cli);
        }

        // Windows has no `which`. `where` is the equivalent.
        return Process::run([windows_os() ? 'where' : 'which', $cli])->successful();
    }

    protected function isExecutableFile(string $path): bool
    {
        if (is_file($path) && is_executable($path)) {
            return true;
        }

        if (! windows_os()) {
            return false;
        }

        // Windows binaries carry an extension that the configured path does
        // not: "C:\tools\mysql" is "C:\tools\mysql.exe" on disk.
        foreach (explode(';', (string) (getenv('PATHEXT') ?: '.EXE;.BAT;.CMD')) as $extension) {
            if (is_file($path.strtolower($extension))) {
                return true;
            }
        }

        return false;
    }
}
