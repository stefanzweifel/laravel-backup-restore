<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Prompts\Prompt;
use Spatie\Backup\Helpers\Format;
use Wnx\LaravelBackupRestore\Actions\CheckDependenciesAction;
use Wnx\LaravelBackupRestore\Actions\CleanupLocalBackupAction;
use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\Actions\ImportDumpAction;
use Wnx\LaravelBackupRestore\Actions\ResetDatabaseAction;
use Wnx\LaravelBackupRestore\Actions\VerifyDumpsAction;
use Wnx\LaravelBackupRestore\Exceptions\BackupRestoreException;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;
use Wnx\LaravelBackupRestore\Exceptions\InvalidHealthCheck;
use Wnx\LaravelBackupRestore\Exceptions\NoBackupsFound;
use Wnx\LaravelBackupRestore\HealthChecks\HealthCheck;
use Wnx\LaravelBackupRestore\HealthChecks\Result;
use Wnx\LaravelBackupRestore\PendingRestore;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class RestoreCommand extends Command
{
    public $signature = 'backup:restore
                        {--disk= : The disk from where to restore the backup from. Defaults to the first disk in config/backup.php.}
                        {--backup= : The backup to restore. Defaults to the latest backup.}
                        {--connection= : The database connection to restore the backup to. Defaults to the first connection in config/backup.php.}
                        {--password= : The password to decrypt the backup.}
                        {--reset : Drop all tables in the database before restoring the backup.}
                        {--keep : Keeps the downloaded zip and decrypted folder from the backup in existence. Handy for moving files afterwards by hand to the correct location.}
                        ';

    public $description = 'Restore a database backup dump from a given disk to a database connection.';

    public function handle(
        CheckDependenciesAction $checkDependenciesAction,
        DownloadBackupAction $downloadBackupAction,
        DecompressBackupAction $decompressBackupAction,
        ResetDatabaseAction $resetDatabaseAction,
        VerifyDumpsAction $verifyDumpsAction,
        ImportDumpAction $importDumpAction,
        CleanupLocalBackupAction $cleanupLocalBackupAction
    ): int {
        Prompt::fallbackWhen(
            ! $this->input->isInteractive() || windows_os() || app()->runningUnitTests()
        );

        // Set once the restore is past the confirmation prompt, so that the
        // finally block only cleans up files this run actually created.
        $startedRestore = null;

        try {
            $connectionOption = $this->option('connection')
                ?? Arr::first(Config::array('backup.backup.source.databases'));
            $connection = is_string($connectionOption) ? $connectionOption : '';

            // Before anything is downloaded: a missing database client is worth
            // knowing about now rather than after a multi-gigabyte download.
            $checkDependenciesAction->execute($connection);

            $diskToRestoreFrom = $this->getDestinationDiskToRestoreFrom();

            $pendingRestore = PendingRestore::make(
                disk: $diskToRestoreFrom,
                backup: $this->getBackupToRestore($diskToRestoreFrom),
                connection: $connection,
                backupPassword: $this->getPassword(),
            );

            if (! $this->confirmRestoreProcess($pendingRestore)) {
                warning('Abort.');

                return self::INVALID;
            }

            $startedRestore = $pendingRestore;

            $downloadBackupAction->execute($pendingRestore);
            $decompressBackupAction->execute($pendingRestore);

            // Find and check the dumps before --reset drops anything. An empty
            // or truncated dump otherwise leaves the database wiped and the
            // original data gone.
            $verifyDumpsAction->execute($pendingRestore, verifyContent: (bool) $this->option('reset'));

            if ($this->option('reset')) {
                $resetDatabaseAction->execute($pendingRestore);
            }

            $importDumpAction->execute($pendingRestore);

            return $this->runHealthChecks($pendingRestore);
        } catch (BackupRestoreException $exception) {
            return $this->renderFailure($exception);
        } finally {
            if ($startedRestore !== null && ! $this->option('keep')) {
                info('Cleaning up …');
                $cleanupLocalBackupAction->execute($startedRestore);
            }
        }
    }

    private function renderFailure(BackupRestoreException $exception): int
    {
        error('Restore failed.');
        error($exception->getMessage());

        $this->writeHint($exception->hint());

        if ($this->output->isVerbose()) {
            warning($exception::class);

            if ($exception instanceof ImportFailed) {
                warning('Exit code: '.($exception->exitCode ?? 'unknown'));
                $this->writeHint($exception->errorOutput);
            }

            $this->line($exception->getTraceAsString());
        }

        return self::FAILURE;
    }

    /**
     * Laravel\Prompts\warning() draws one box per line, which turns captured
     * stderr into a wall of boxes. Print anything multi-line plainly instead.
     */
    private function writeHint(?string $hint): void
    {
        if ($hint === null || trim($hint) === '') {
            return;
        }

        if (str_contains($hint, "\n")) {
            $this->newLine();
            $this->line($hint);

            return;
        }

        warning($hint);
    }

    private function getDestinationDiskToRestoreFrom(): string
    {
        // Use disk from --disk option if provided
        $disk = $this->option('disk');

        if (is_string($disk) && $disk !== '') {
            return $disk;
        }

        $availableDestinations = array_values(array_filter(
            Config::array('backup.backup.destination.disks'),
            is_string(...)
        ));

        // If there is only one disk configured, use it
        if (count($availableDestinations) === 1) {
            return $availableDestinations[0];
        }

        // Ask user to choose a disk
        return (string) select(
            'From which disk should the backup be restored?',
            $availableDestinations,
            $availableDestinations[0] ?? null
        );
    }

    /**
     * @throws NoBackupsFound
     */
    private function getBackupToRestore(string $disk): string
    {
        $backup = $this->option('backup');

        if (is_string($backup) && $backup !== '' && $backup !== 'latest') {
            return $backup;
        }

        $name = Config::string('backup.backup.name');

        info("Fetch list of backups from $disk …");
        $listOfBackups = collect(Storage::disk($disk)->allFiles($name))
            ->filter(fn ($file) => Str::endsWith($file, '.zip'))
            ->values();

        $latestBackup = $listOfBackups->last();

        if ($latestBackup === null) {
            throw NoBackupsFound::onDisk($disk, $name);
        }

        if ($this->option('backup') === 'latest') {
            return $latestBackup;
        }

        $backups = $listOfBackups->map(fn (string $path): array => [
            'path' => $path,
            'size' => Format::humanReadableSize(Storage::disk($disk)->size($path)),
        ]);

        $labelLength = $backups->reduce(
            fn (int $carry, array $backup): int => max($carry, strlen($backup['path'].$backup['size']) + 5),
            60
        );

        return (string) select(
            label: 'Which backup should be restored?',
            options: $this->getBackupOptions($backups, $labelLength)->all(),
            default: $latestBackup,
            scroll: 10
        );
    }

    private function getPassword(): ?string
    {
        if ($this->option('password')) {
            $password = $this->option('password');
        } elseif ($this->option('no-interaction')) {
            $password = config('backup.backup.password');
        } elseif (confirm('Use encryption password from config?', true)) {
            $password = config('backup.backup.password');
        } else {
            $password = password('What is the password to decrypt the backup? (leave empty if not encrypted)');
        }

        return is_string($password) ? $password : null;
    }

    /**
     * @throws InvalidHealthCheck
     */
    private function runHealthChecks(PendingRestore $pendingRestore): int
    {
        $checks = [];

        foreach (Arr::wrap(config('backup-restore.health-checks')) as $check) {
            if (! is_string($check) || ! is_a($check, HealthCheck::class, true)) {
                throw InvalidHealthCheck::notAHealthCheck(is_string($check) ? $check : get_debug_type($check));
            }

            $checks[] = $check;
        }

        $failedResults = collect($checks)
            ->map(fn (string $check): HealthCheck => $check::new())
            ->map(fn (HealthCheck $check): Result => $check->run($pendingRestore))
            ->filter(fn (Result $result): bool => $result->status === self::FAILURE);

        if ($failedResults->count() > 0) {
            $failedResults->each(fn (Result $result) => error(
                $result->message ?? class_basename($result->healthCheck).' failed.'
            ));

            return self::FAILURE;
        }

        info('All health checks passed.');

        return self::SUCCESS;
    }

    private function confirmRestoreProcess(PendingRestore $pendingRestore): bool
    {
        $connectionConfig = config("database.connections.{$pendingRestore->connection}");
        $connectionConfig = is_array($connectionConfig) ? $connectionConfig : [];

        $connectionInformation = [];

        foreach (['Database' => 'database', 'Host' => 'host', 'username' => 'username'] as $label => $key) {
            $value = Arr::get($connectionConfig, $key);

            if (! is_scalar($value) || ! $value) {
                continue;
            }

            $connectionInformation[] = "{$label}: {$value}";
        }

        $connectionInformationForConfirmation = implode(', ', $connectionInformation);

        $label = sprintf(
            'Proceed to restore "%s" using the "%s" database connection. (%s)',
            $pendingRestore->backup,
            $pendingRestore->connection,
            $connectionInformationForConfirmation
        );

        if ($this->option('reset')) {
            $label .= ' This drops all tables in that database and cannot be undone.';
        }

        return confirm(label: $label, default: true);
    }

    /**
     * @param  Collection<int, array{path: string, size: string}>  $listOfBackups
     * @return Collection<string, string>
     */
    protected function getBackupOptions(Collection $listOfBackups, int $labelLength): Collection
    {
        return $listOfBackups->mapWithKeys(fn (array $backup): array => [
            $backup['path'] => $this->getBackupOptionLabel($backup, $labelLength),
        ]);
    }

    /**
     * @param  array{path: string, size: string}  $backup
     */
    protected function getBackupOptionLabel(array $backup, int $labelLength): string
    {
        return str_pad($backup['path'].' ', ($labelLength - strlen($backup['size'])), '.', STR_PAD_RIGHT).' '.$backup['size'];
    }
}
