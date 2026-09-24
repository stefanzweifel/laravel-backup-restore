<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SensitiveParameter;

class PendingRestore
{
    public function __construct(
        public readonly string $disk,
        public readonly string $backup,
        public readonly string $connection,
        public readonly string $restoreId,
        public readonly string $restoreName,
        #[SensitiveParameter] public readonly ?string $backupPassword = null,
        public readonly string $restoreDisk = 'local',
    ) {
        //
    }

    public static function make(
        string $disk,
        string $backup,
        string $connection,
        #[SensitiveParameter] ?string $backupPassword = null,
        string $restoreDisk = 'local',
    ): PendingRestore {
        $restoreName = now()->format('Y-m-d-h-i-s').'-'.Str::uuid();

        return new self(
            disk: $disk,
            backup: $backup,
            connection: $connection,
            restoreId: $restoreName,
            restoreName: $restoreName,
            backupPassword: $backupPassword,
            restoreDisk: $restoreDisk,
        );
    }

    public function getFileExtensionOfRemoteBackup(): string
    {
        return pathinfo($this->backup, PATHINFO_EXTENSION);
    }

    public function getPathToLocalCompressedBackup(): string
    {
        $filename = "$this->restoreId.{$this->getFileExtensionOfRemoteBackup()}";

        return 'backup-restore-temp/'.$filename;
    }

    public function getPathToLocalDecompressedBackup(): string
    {
        $filename = $this->restoreId;

        return 'backup-restore-temp/'.$filename;
    }

    public function getAbsolutePathToLocalDecompressedBackup(): string
    {
        $filename = $this->restoreId;
        $root = Config::string('filesystems.disks.local.root');

        return $root.DIRECTORY_SEPARATOR.'backup-restore-temp'.DIRECTORY_SEPARATOR.$filename;
    }

    /** @deprecated  */
    public function hasNoDbDumpsDirectory(): bool
    {
        return ! Storage::disk($this->restoreDisk)
            ->has($this->getPathToLocalDecompressedBackup().'/db-dumps');
    }

    /**
     * @return Collection<int, string>
     */
    public function getAvailableFilesInDbDumpsDirectory(): Collection
    {
        $files = Storage::disk($this->restoreDisk)
            ->files($this->getPathToLocalDecompressedBackup().'/db-dumps');

        return collect($files);
    }

    /**
     * @return Collection<int, string>
     */
    public function getAvailableDbDumps(): Collection
    {
        $backupDatabaseDumpFileExtension = Config::string('backup.backup.database_dump_file_extension', 'sql');
        $backupDatabaseDumpFileExtensionWithLeadingDot = ".{$backupDatabaseDumpFileExtension}";

        return $this->getAvailableFilesInDbDumpsDirectory()
            ->filter(fn ($file) => preg_match('/^[A-Za-z0-9._-]+$/', basename($file)) === 1)
            ->filter(fn ($file) => Str::endsWith($file, ['.sql', '.sql.gz', '.sql.bz2', $backupDatabaseDumpFileExtensionWithLeadingDot]));
    }
}
