<?php

declare(strict_types=1);

namespace Blafast\Foundation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a cache miss occurs.
 *
 * Useful for monitoring cache performance and identifying
 * frequently missed keys.
 */
class MetadataCacheMiss
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $key  Cache key that was missed
     * @param  string  $type  Type of cache (metadata, menu, settings)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type,
    ) {}
}
