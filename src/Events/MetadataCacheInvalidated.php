<?php

declare(strict_types=1);

namespace Blafast\Foundation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when metadata cache is invalidated.
 *
 * Can be used for monitoring, logging, or triggering additional actions.
 */
class MetadataCacheInvalidated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<int, string>  $tags  Cache tags that were invalidated
     */
    public function __construct(
        public readonly array $tags,
    ) {}
}
