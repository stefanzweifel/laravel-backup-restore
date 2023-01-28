<?php

namespace Wnx\LaravelBackupRestore\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Wnx\LaravelBackupRestore\LaravelBackupRestore
 */
class LaravelBackupRestore extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Wnx\LaravelBackupRestore\LaravelBackupRestore::class;
    }
}
