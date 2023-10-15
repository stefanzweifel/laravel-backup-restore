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
        $databaseCli = DbImporterFactory::createFromConnection($connection)->getCliName();

        $this->checkIfCliExists($databaseCli);

        $this->checkIfCliExists('gunzip');
    }

    /**
     * @throws CliNotFound|\Throwable
     */
    protected function checkIfCliExists($cli): void
    {
        $result = Process::run(['which', $cli]);

        throw_if($result->failed(), CliNotFound::create($cli));
    }
}
