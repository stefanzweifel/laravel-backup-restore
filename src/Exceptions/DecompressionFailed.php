<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Exceptions;

use Exception;
use ZipArchive;

class DecompressionFailed extends Exception
{
    public static array $errorCodeToMessage = [
        ZipArchive::ER_EXISTS => 'The file %filename% already exists. (ZipArchive::ER_EXISTS)',
        ZipArchive::ER_INCONS => 'The zip archive is inconsistent. (ZipArchive::ER_INCONS)',
        ZipArchive::ER_INVAL => 'Invalid argument. (ZipArchive::ER_INVAL)',
        ZipArchive::ER_MEMORY => 'Malloc failure. (ZipArchive::ER_MEMORY)',
        ZipArchive::ER_NOENT => 'No such file. (ZipArchive::ER_NOENT)',
        ZipArchive::ER_NOZIP => 'Not a zip archive. (ZipArchive::ER_NOZIP)',
        ZipArchive::ER_OPEN => 'The file %filename% can\'t be opened. (ZipArchive::ER_OPEN)',
        ZipArchive::ER_READ => 'The file %filename% can\'t be rode. (ZipArchive::ER_READ)',
        ZipArchive::ER_SEEK => 'The file %filename% can\'t be sought. (ZipArchive::ER_SEEK)',
    ];

    public static function create($errorCode, $filename): static
    {
        return new static(str_replace(
            '%filename%',
            $filename,
            self::$errorCodeToMessage[$errorCode] ?? 'Unknown error.'
        )
        );
    }
}
