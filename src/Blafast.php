<?php

declare(strict_types=1);

namespace Blafast\Foundation;

class Blafast
{
    /**
     * Get the version of the Blafast Foundation module.
     */
    public static function version(): string
    {
        return '1.0.0';
    }

    /**
     * Get the module name.
     */
    public static function name(): string
    {
        return 'Blafast Foundation';
    }
}
