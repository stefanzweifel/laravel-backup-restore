<?php

declare(strict_types=1);

return [

    /*
     * The database dump can be compressed to decrease diskspace usage.
     *
     * Out of the box Laravel-backup supplies
     * Spatie\DbDumper\Compressors\GzipCompressor::class.
     *
     * You can also create custom compressor. More info on that here:
     * https://github.com/spatie/db-dumper#using-compression
     *
     * If you do not want any compressor at all, set it to null.
     */
    'database_dump_compressor' => null,

    'temporary_directory' => storage_path('app/backup-temp'),

    /*
     * The password to be used for archive encryption.
     * Set to `null` to disable encryption.
     */
    'password' => env('BACKUP_ARCHIVE_PASSWORD'),

    /*
     * The encryption algorithm to be used for archive encryption.
     * You can set it to `null` or `false` to disable encryption.
     *
     * When set to 'default', we'll use ZipArchive::EM_AES_256 if it is
     * available on your system.
     */
    'encryption' => 'default',

    'gunzip' => '/usr/bin/gunzip',
    'zcat' => '/usr/bin/zcat',

];
