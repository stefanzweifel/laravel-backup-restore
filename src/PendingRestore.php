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
        readonly public string $disk,
        readonly public string $backup,
        readonly public string $connection,
        readonly public string $restoreId,
        readonly public string $restoreName,
        #[SensitiveParameter] readonly public ?string $backupPassword = null,
        readonly public string $restoreDisk = 'local',
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

        return storage_path('app'.DIRECTORY_SEPARATOR.'backup-restore-temp'.DIRECTORY_SEPARATOR.$filename);
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
        return $this->getAvailableFilesInDbDumpsDirectory()
            ->filter(fn ($file) => Str::endsWith($file, ['.sql', '.sql.gz', '.sql.bz2']));
    }
}
