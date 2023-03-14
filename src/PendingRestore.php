<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore;

use Illuminate\Contracts\Filesystem\Filesystem;
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

        return "backup-restore-temp/$filename";
    }

    public function getPathToLocalDecompressedBackup(): string
    {
        $filename = $this->restoreId;

        return "backup-restore-temp/$filename";
    }

    public function getAbsolutePathToLocalDecompressedBackup(): string
    {
        $filename = $this->restoreId;

        return storage_path("app/backup-restore-temp/$filename");
    }

    public function hasNoDbDumpsDirectory(): bool
    {
        return ! Storage::disk($this->restoreDisk)
            ->has("{$this->getPathToLocalDecompressedBackup()}/db-dumps");
    }

    public function getAvailableDbDumps(): array
    {
        return Storage::disk($this->restoreDisk)
            ->files("{$this->getPathToLocalDecompressedBackup()}/db-dumps");
    }
}
