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
        // Extract user from event
        $user = $this->extractUser($event);

        if (! $user) {
            return;
        }

        // Invalidate menu cache for the affected user
        $this->cache->invalidateMenuForUser(
            $user->getAuthIdentifier(),
            $this->context->id()
        );

        // If organization context exists, invalidate org metadata
        if ($this->context->hasContext()) {
            $this->cache->invalidateOrganization($this->context->id());
        }
    }

    /**
     * Extract user from event.
     *
     * @param  object  $event  Event object
     * @return Authenticatable|null
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
