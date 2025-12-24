<?php

namespace Blafast\Blafast\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Blafast\Blafast\Blafast
 */
class Blafast extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Blafast\Blafast\Blafast::class;
    }
}
