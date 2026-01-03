<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Dto\MenuItem;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Service for building and filtering user-specific menus.
 *
 * This service retrieves menu items from MenuRegistry and filters them
 * based on user permissions, providing a hierarchical menu structure
 * authorized for the authenticated user.
 */
class MenuService
{
    /**
     * Create a new menu service instance.
     */
    public function __construct(
        private MenuRegistry $registry,
        private MetadataCacheService $cache,
        private OrganizationContext $context,
    ) {}

    /**
     * Get filtered menu for user with caching.
     *
     * @return array<MenuItem>
     */
    public function getForUser(Authenticatable $user): array
    {
        $cacheKey = $this->getCacheKey($user);
        $tags = $this->getCacheTags($user);

        return $this->cache->remember(
            $cacheKey,
            $tags,
            fn () => $this->buildMenu($user),
            config('blafast-fundation.cache.menu_ttl', 600)
        );
    }

    /**
     * Build menu filtered by user permissions.
     *
     * @return array<MenuItem>
     */
    protected function buildMenu(Authenticatable $user): array
    {
        $allItems = $this->registry->all();

        return $this->filterItems($allItems, $user);
    }

    /**
     * Recursively filter items by permission.
     *
     * @param  array<MenuItem>  $items
     * @return array<MenuItem>
     */
    protected function filterItems(array $items, Authenticatable $user): array
    {
        $filtered = [];

        foreach ($items as $item) {
            // Check if user has permission for this item
            if (! $this->userCanAccess($user, $item)) {
                continue;
            }

            // Filter children recursively
            $filteredChildren = $this->filterItems($item->children, $user);

            // Include item if it has no permission requirement,
            // or user has permission, and either:
            // - item has a route/url, or
            // - item has visible children
            if ($item->route || $item->url || ! empty($filteredChildren)) {
                $filtered[] = $item->withChildren($filteredChildren);
            }
        }

        return $filtered;
    }

    /**
     * Check if user can access menu item.
     */
    protected function userCanAccess(Authenticatable $user, MenuItem $item): bool
    {
        // No permission required
        if (! $item->permission) {
            return true;
        }

        // Check permission with organization context
        return $user->can($item->permission);
    }

    /**
     * Get cache key for user menu.
     */
    protected function getCacheKey(Authenticatable $user): string
    {
        $orgId = $this->context->id() ?? 'global';

        return "menu:user:{$user->getAuthIdentifier()}:org:{$orgId}";
    }

    /**
     * Get cache tags for user menu.
     *
     * @return array<string>
     */
    protected function getCacheTags(Authenticatable $user): array
    {
        $tags = [
            'menu',
            "user-{$user->getAuthIdentifier()}",
        ];

        if ($this->context->hasContext()) {
            $tags[] = "org-{$this->context->id()}";
        }

        // Add module tags from registered menu items
        foreach ($this->registry->all() as $item) {
            if ($item->tag) {
                $tags[] = $item->tag;
            }

            // Add child tags
            $this->collectTags($item->children, $tags);
        }

        return array_unique($tags);
    }

    /**
     * Recursively collect tags from menu items.
     *
     * @param  array<MenuItem>  $items
     * @param  array<string>  $tags
     */
    protected function collectTags(array $items, array &$tags): void
    {
        foreach ($items as $item) {
            if ($item->tag) {
                $tags[] = $item->tag;
            }

            if (! empty($item->children)) {
                $this->collectTags($item->children, $tags);
            }
        }
    }

    /**
     * Invalidate menu cache for user.
     */
    public function invalidateForUser(Authenticatable $user): void
    {
        $this->cache->invalidateByTags([
            'menu',
            "user-{$user->getAuthIdentifier()}",
        ]);
    }
}
