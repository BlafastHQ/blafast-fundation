<?php

declare(strict_types=1);

namespace Blafast\Foundation\Listeners;

use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Listener to invalidate metadata cache when permissions change.
 *
 * Invalidates menu and metadata caches for affected users when
 * their roles or permissions are synced.
 */
class InvalidateMetadataCacheOnPermissionChange
{
    public function __construct(
        private MetadataCacheService $cache,
        private OrganizationContext $context,
    ) {}

    /**
     * Handle the permission change event.
     *
     * @param  object  $event  Permission sync event
     */
    public function handle(object $event): void
    {
        // Permission topology changed. The stale entries belong to an UNKNOWN set
        // of users: role-targeted events (givePermissionTo on a role) carry the
        // ROLE as the event model, and even direct user grants tag no user-specific
        // meta entries. Invalidate the metadata cache wholesale — these events are
        // rare and the cache rebuilds lazily (task 13; the old code returned
        // silently for role events).
        $this->cache->invalidateAll();

        // Belt-and-suspenders for the user-addressable menu keys.
        if ($user = $this->extractUser($event)) {
            $this->cache->invalidateMenuForUser(
                $user->getAuthIdentifier(),
                $this->context->id()
            );
        }
    }

    /**
     * Extract user from event.
     *
     * @param  object  $event  Event object
     */
    protected function extractUser(object $event): ?Authenticatable
    {
        if (property_exists($event, 'user') && $event->user instanceof Authenticatable) {
            return $event->user;
        }

        if (property_exists($event, 'model') && $event->model instanceof Authenticatable) {
            return $event->model;
        }

        return null;
    }
}
