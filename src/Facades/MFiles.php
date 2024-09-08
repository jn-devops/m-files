<?php

namespace Homeful\MFiles\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Homeful\MFiles\MFiles
 */
class MFiles extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Homeful\MFiles\MFiles::class;
    }
}
