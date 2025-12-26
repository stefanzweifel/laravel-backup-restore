<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore;

use Illuminate\Support\Collection;
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

    public static function make(...$attributes): PendingRestore
    {
        $restoreName = now()->format('Y-m-d-h-i-s').'-'.Str::uuid();

        /** @phpstan-ignore-next-line */
        return new self(
            ...$attributes,
            restoreName: $restoreName,
            restoreId: $restoreName,
        );
    }

    public function getFileExtensionOfRemoteBackup(): string
    {
        return pathinfo($this->backup, PATHINFO_EXTENSION);
    }

    public function getPathToLocalCompressedBackup(): string
    {
        $filename = "$this->restoreId.{$this->getFileExtensionOfRemoteBackup()}";

        return 'backup-restore-temp'.DIRECTORY_SEPARATOR.$filename;
    }

    public function getPathToLocalDecompressedBackup(): string
    {
        $filename = $this->restoreId;

        return 'backup-restore-temp'.DIRECTORY_SEPARATOR.$filename;
    }

    public function getAbsolutePathToLocalDecompressedBackup(): string
    {
        $filename = $this->restoreId;
        $root = config('filesystems.disks.local.root');

        return $root.DIRECTORY_SEPARATOR.'backup-restore-temp'.DIRECTORY_SEPARATOR.$filename;
    }

    /** @deprecated  */
    public function hasNoDbDumpsDirectory(): bool
    {
        return ! Storage::disk($this->restoreDisk)
            ->has($this->getPathToLocalDecompressedBackup().DIRECTORY_SEPARATOR.'db-dumps');
    }

    public function getAvailableFilesInDbDumpsDirectory(): Collection
    {
        $files = Storage::disk($this->restoreDisk)
            ->files($this->getPathToLocalDecompressedBackup().DIRECTORY_SEPARATOR.'db-dumps');

        return collect($files);
    }

    public function getAvailableDbDumps(): Collection
    {
        $backupDatabaseDumpFileExtension = config('backup.backup.database_dump_file_extension', 'sql');
        $backupDatabaseDumpFileExtensionWithLeadingDot = ".{$backupDatabaseDumpFileExtension}";

        return $this->getAvailableFilesInDbDumpsDirectory()
            ->filter(fn ($file) => Str::endsWith($file, ['.sql', '.sql.gz', '.sql.bz2', $backupDatabaseDumpFileExtensionWithLeadingDot]))
            ->dump();
    }
}
