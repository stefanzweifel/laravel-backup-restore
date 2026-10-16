<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Commands;

use Closure;
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
use Wnx\LaravelBackupRestore\Events\RestoreAborted;
use Wnx\LaravelBackupRestore\Exceptions\BackupRestoreException;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;
use Wnx\LaravelBackupRestore\Exceptions\InvalidHealthCheck;
use Wnx\LaravelBackupRestore\Exceptions\NoBackupsFound;
use Wnx\LaravelBackupRestore\Exceptions\RestoreWasAborted;
use Wnx\LaravelBackupRestore\HealthChecks\HealthCheck;
use Wnx\LaravelBackupRestore\HealthChecks\Result;
use Wnx\LaravelBackupRestore\PendingRestore;
use Wnx\LaravelBackupRestore\RestoreAbort;

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

        $abort = new RestoreAbort;
        $commandPid = getmypid();

        // Without ext-pcntl this registers nothing and the command behaves exactly as it
        // did before: a signal kills the process outright and its temporary files stay.
        //
        // SIGINT does not reach this handler while a spin() is running: Spinner::spin()
        // installs its own `exit()` handler for it (laravel/prompts Spinner.php:57) and
        // restores the previous one afterwards. SIGTERM and SIGHUP are unaffected.
        $this->trap($this->signalsToTrap(), function (int $signal) use ($abort, $commandPid): void {
            $pid = getmypid();

            // Laravel Prompts' spinner forks a child that inherits this handler, and
            // Spinner::__destruct() ends that child with SIGHUP. It has to die of the
            // signal the way it did before the trap existed, not record an abort.
            if (! is_int($pid) || ! is_int($commandPid) || $pid !== $commandPid) {
                $this->dieOfSignal($signal);
            }

            // A second signal means the user is done waiting. Nothing is printed, and
            // whatever the first abort was still cleaning up is left where it is.
            if ($abort->wasRequested()) {
                $this->dieOfSignal($signal);
            }

            $abort->requestAbort($signal);
        });

        // Set once the restore is past the confirmation prompt, so that the
        // finally block only cleans up files this run actually created.
        $startedRestore = null;

        // Together these two decide the cleanup: the local files are kept only
        // when the database was being changed and the import did not finish, so
        // the extracted dump is the only copy of what was going in. A completed
        // import — health checks passing or not — cleans up as it always did.
        $databaseWasTouched = false;
        $importCompleted = false;

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

            // A signal that arrived while the user was answering the prompts, before
            // the download pulls the whole archive down for nothing.
            $this->guardAgainstAbort($abort, databaseWasTouched: false);

            $downloadBackupAction->execute($pendingRestore);

            // The download is not interruptible: Storage::writeStream() blocks inside
            // flysystem. A signal that arrived during it is picked up here.
            $this->guardAgainstAbort($abort, databaseWasTouched: false);

            $decompressBackupAction->execute($pendingRestore, $abort);

            // Find and check the dumps before --reset drops anything. An empty
            // or truncated dump otherwise leaves the database wiped and the
            // original data gone.
            $verifyDumpsAction->execute($pendingRestore, verifyContent: (bool) $this->option('reset'));

            $this->guardAgainstAbort($abort, databaseWasTouched: false);

            if ($this->option('reset')) {
                $databaseWasTouched = true;

                $resetDatabaseAction->execute($pendingRestore);
            }

            $databaseWasTouched = true;

            $importDumpAction->execute($pendingRestore, $abort);

            $importCompleted = true;

            return $this->runHealthChecks($pendingRestore);
        } catch (RestoreWasAborted $exception) {
            // The exception carries what the throwing layer knew. The command knows
            // whether --reset or the import had started, which is the wider fact.
            $databaseWasTouched = $databaseWasTouched || $exception->databaseWasTouched;

            if ($startedRestore !== null) {
                event(new RestoreAborted($startedRestore, $exception->signal, $databaseWasTouched));
            }

            return $this->renderAbort($exception, $databaseWasTouched);
        } catch (BackupRestoreException $exception) {
            return $this->renderFailure($exception);
        } finally {
            if ($startedRestore !== null && ! $this->option('keep') && (! $databaseWasTouched || $importCompleted)) {
                info('Cleaning up …');
                $cleanupLocalBackupAction->execute($startedRestore);
            }

            // Last, so that a second signal can still interrupt a slow directory
            // delete. Removing the trap matters for a long-lived process that runs
            // this command through Artisan::call(): the closure would otherwise stay
            // registered and handle a later, unrelated signal.
            $this->untrap();
        }
    }

    /**
     * The signals to trap, resolved lazily. Illuminate\Console\Signals only calls
     * this when ext-pcntl is loaded, which is the only time the constants exist.
     *
     * @return Closure(): array<int, int>
     */
    private function signalsToTrap(): Closure
    {
        return static fn (): array => array_map(
            static fn (string $name): int => (int) constant($name),
            array_values(array_filter(
                ['SIGINT', 'SIGTERM', 'SIGHUP'],
                static fn (string $name): bool => defined($name),
            )),
        );
    }

    /**
     * A second signal. Put the signal back to its default disposition and re-raise
     * it, so the process dies the way it would have without the trap. Anything the
     * first signal was still cleaning up is left where it is, which is the point.
     */
    private function dieOfSignal(int $signal): never
    {
        $this->untrap();

        if (function_exists('pcntl_signal') && function_exists('posix_kill') && function_exists('posix_getpid')) {
            pcntl_signal($signal, SIG_DFL);
            posix_kill(posix_getpid(), $signal);
        }

        exit(128 + $signal);
    }

    /**
     * @throws RestoreWasAborted
     */
    private function guardAgainstAbort(RestoreAbort $abort, bool $databaseWasTouched): void
    {
        if ($abort->wasRequested()) {
            throw RestoreWasAborted::bySignal($abort->signal() ?? 0, $databaseWasTouched);
        }
    }

    private function renderAbort(RestoreWasAborted $exception, bool $databaseWasTouched): int
    {
        warning($exception->getMessage());

        if ($databaseWasTouched) {
            error('The database may hold a partial restore.');
        }

        $this->writeHint($exception->hint());
        $this->writeHint($this->describeLocalFiles($databaseWasTouched));

        if ($this->output->isVerbose() && ($previous = $exception->getPrevious()) !== null) {
            warning($previous::class);
            $this->writeHint($previous->getMessage());
        }

        return $exception->exitCode();
    }

    /**
     * What happened to the downloaded archive and the extracted dump. The
     * exception cannot say: it knows the database state but not --keep. The
     * finally block above decides the same way.
     */
    private function describeLocalFiles(bool $databaseWasTouched): string
    {
        if ($databaseWasTouched) {
            return 'The downloaded files were kept so you can inspect the dump or finish the import by hand.';
        }

        if ($this->option('keep')) {
            return 'The downloaded files were kept because of --keep.';
        }

        return 'The downloaded files were removed.';
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
