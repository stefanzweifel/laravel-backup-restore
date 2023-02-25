<?php

namespace Wnx\LaravelBackupRestore;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PendingRestore
{
    public function __construct(
        readonly public string $disk,
        readonly public string $backup,
        readonly public string $connection,
        readonly public string $restoreId,
        readonly public string $restoreName,
        #[\SensitiveParameter] readonly public ?string $backupPassword = null,
        readonly public string $restoreDisk = 'local',
        // public string $pathToLocalRestore = '',
        // public string $pathToLocalBackup = '',
        // public string $pathToDecompressedBackup = '',

        // TODO
        // Absolute path to unzipped backup
    ) {
        //
    }

    public static function make(...$attributes): PendingRestore
    {
        $restoreName = now()->format('Y-m-d-h-i-s').'-'.Str::ulid();

        $pathToStoreBackup = '';

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
        // TODO: Incorporate temp directory of config into path.
        // config('backup-restore.temporary_directory');

        $filename = "{$this->restoreId}.{$this->getFileExtensionOfRemoteBackup()}";

        return "backup-restore-temp/$filename";
    }

    public function getPathToLocalDecompressedBackup(): string
    {
        // TODO: Incorporate temp directory of config into path.
        // config('backup-restore.temporary_directory');

        $filename = $this->restoreId;

        return "backup-restore-temp/$filename";
    }

    public function getAbsolutePathToLocalDecompressedBackup(): string
    {
        // TODO: Incorporate temp directory of config into path.
        // config('backup-restore.temporary_directory');

        $filename = $this->restoreId;

        return storage_path("app/backup-restore-temp/$filename");
    }

    // Experimental

    /**
     * Generate On-Demand Disk
     * https://laravel.com/docs/9.x/filesystem#on-demand-disks
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function getLocalRestoreDisk()
    {
        return Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/backup-restore-temp'),
        ]);
    }
}
