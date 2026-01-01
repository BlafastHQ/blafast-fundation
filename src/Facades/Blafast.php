<?php

declare(strict_types=1);

namespace Blafast\Foundation\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Blafast\Foundation\Blafast
 */
class Blafast extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Blafast\Foundation\Blafast::class;
    }
}
