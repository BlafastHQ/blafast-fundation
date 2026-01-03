<?php

declare(strict_types=1);

namespace Blafast\Foundation\Facades;

use Blafast\Foundation\Dto\MenuItem;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for MenuRegistry service.
 *
 * @method static self add(array|MenuItem $item)
 * @method static self addMany(array $items)
 * @method static array all()
 * @method static ?MenuItem getByTag(string $tag)
 * @method static bool hasTag(string $tag)
 * @method static void flushTag(string $tag)
 * @method static void clear()
 *
 * @see \Blafast\Foundation\Services\MenuRegistry
 */
class MenuRegistry extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Blafast\Foundation\Services\MenuRegistry::class;
    }
}
