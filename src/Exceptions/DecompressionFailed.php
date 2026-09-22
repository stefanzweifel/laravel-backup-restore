<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;
use ZipArchive;

class DecompressionFailed extends Exception implements BackupRestoreException
{
    /** @var array<int, string> */
    public static array $errorCodeToMessage = [
        false => 'The archive could not be opened or extracted.',
        ZipArchive::ER_EXISTS => 'The file already exists. (ZipArchive::ER_EXISTS)',
        ZipArchive::ER_INCONS => 'The zip archive is inconsistent. (ZipArchive::ER_INCONS)',
        ZipArchive::ER_INVAL => 'Invalid argument. (ZipArchive::ER_INVAL)',
        ZipArchive::ER_MEMORY => 'Malloc failure. (ZipArchive::ER_MEMORY)',
        ZipArchive::ER_NOENT => 'No such file. (ZipArchive::ER_NOENT)',
        ZipArchive::ER_NOZIP => 'Not a zip archive. (ZipArchive::ER_NOZIP)',
        ZipArchive::ER_OPEN => 'The file could not be opened. (ZipArchive::ER_OPEN)',
        ZipArchive::ER_READ => 'The file could not be read. (ZipArchive::ER_READ)',
        ZipArchive::ER_SEEK => 'The file could not be sought. (ZipArchive::ER_SEEK)',
    ];

    protected function __construct(
        public readonly string $archive,
        public readonly int|false|null $errorCode,
        public readonly ?string $entryName,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function pathTraversalDetected(string $entryName, string $archive): static
    {
        return new static(
            archive: $archive,
            errorCode: null,
            entryName: $entryName,
            message: "The ZIP entry \"{$entryName}\" in \"{$archive}\" was rejected as a path traversal attempt.",
        );
    }

    public static function create(int|bool $errorCode, string $filename): static
    {
        $reason = self::$errorCodeToMessage[$errorCode] ?? 'Unknown error.';

        return new static(
            archive: $filename,
            errorCode: $errorCode === true ? null : $errorCode,
            entryName: null,
            message: "Decompressing \"{$filename}\" failed: {$reason}",
        );
    }

    public function hint(): ?string
    {
        if ($this->entryName !== null) {
            return 'The archive was not created by spatie/laravel-backup or has been tampered with.';
        }

        if ($this->errorCode === false) {
            return 'If the backup is encrypted, pass --password or set backup.backup.password.';
        }

        if ($this->errorCode === ZipArchive::ER_NOZIP) {
            return 'Check that the file is a ZIP archive created by spatie/laravel-backup.';
        }

        return null;
    }
}
