<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Dto\MenuItem;

/**
 * Registry for managing application menu items.
 *
 * This service provides centralized menu management with support for:
 * - Hierarchical menu structures
 * - Tag-based merging for module extensions
 * - Order-based sorting
 * - Cache invalidation
 */
class MenuRegistry
{
    /**
     * Untagged menu items.
     *
     * @var array<int, MenuItem>
     */
    private array $items = [];

    /**
     * Tagged menu items indexed by tag.
     *
     * @var array<string, MenuItem>
     */
    private array $taggedItems = [];

    /**
     * Whether items have been sorted.
     */
    private bool $sorted = false;

    /**
     * Add a menu item to the registry.
     *
     * @param  array<string, mixed>|MenuItem  $item
     */
    public function add(array|MenuItem $item): self
    {
        if (is_array($item)) {
            $item = MenuItem::fromArray($item);
        }

        if ($item->tag) {
            $this->addOrMergeTagged($item);
        } else {
            $this->items[] = $item;
        }

        $this->sorted = false;

        return $this;
    }

    /**
     * Add multiple menu items.
     *
     * @param  array<array<string, mixed>|MenuItem>  $items
     */
    public function addMany(array $items): self
    {
        foreach ($items as $item) {
            $this->add($item);
        }

        return $this;
    }

    /**
     * Get all registered menu items, sorted by order.
     *
     * @return array<MenuItem>
     */
    public function all(): array
    {
        if (! $this->sorted) {
            $this->sort();
        }

        return array_merge(
            array_values($this->taggedItems),
            $this->items
        );
    }

    /**
     * Get menu item by tag.
     */
    public function getByTag(string $tag): ?MenuItem
    {
        return $this->taggedItems[$tag] ?? null;
    }

    /**
     * Check if a tag exists.
     */
    public function hasTag(string $tag): bool
    {
        return isset($this->taggedItems[$tag]);
    }

    /**
     * Remove a tag and invalidate its cache.
     */
    public function flushTag(string $tag): void
    {
        unset($this->taggedItems[$tag]);

        // Invalidate cache
        app(MetadataCacheService::class)->invalidateByTags(['menu', $tag]);
    }

    /**
     * Clear all registered items (useful for testing).
     */
    public function clear(): void
    {
        $this->items = [];
        $this->taggedItems = [];
        $this->sorted = false;
    }

    /**
     * Add or merge a tagged item with existing.
     */
    private function addOrMergeTagged(MenuItem $item): void
    {
        $tag = $item->tag;

        if ($tag === null) {
            return;
        }

        if (isset($this->taggedItems[$tag])) {
            $this->taggedItems[$tag] = $this->merge(
                $this->taggedItems[$tag],
                $item
            );
        } else {
            $this->taggedItems[$tag] = $item;
        }
    }

    /**
     * Merge two menu items.
     *
     * New properties override existing, except children which merge.
     */
    private function merge(MenuItem $existing, MenuItem $new): MenuItem
    {
        // Merge children by tag
        $mergedChildren = $this->mergeChildren(
            $existing->children,
            $new->children
        );

        // New properties override existing, except children which merge
        return new MenuItem(
            label: $new->label ?: $existing->label,
            icon: $new->icon ?? $existing->icon,
            permission: $new->permission ?? $existing->permission,
            route: $new->route ?? $existing->route,
            url: $new->url ?? $existing->url,
            order: $new->order !== 100 ? $new->order : $existing->order,
            tag: $existing->tag,
            children: $mergedChildren,
        );
    }

    /**
     * Merge children arrays.
     *
     * @param  array<MenuItem>  $existing
     * @param  array<MenuItem>  $new
     * @return array<MenuItem>
     */
    private function mergeChildren(array $existing, array $new): array
    {
        // Index existing by tag
        $byTag = [];
        $untagged = [];

        foreach ($existing as $child) {
            if ($child->tag) {
                $byTag[$child->tag] = $child;
            } else {
                $untagged[] = $child;
            }
        }

        // Merge new children
        foreach ($new as $child) {
            if ($child->tag && isset($byTag[$child->tag])) {
                $byTag[$child->tag] = $this->merge($byTag[$child->tag], $child);
            } elseif ($child->tag) {
                $byTag[$child->tag] = $child;
            } else {
                $untagged[] = $child;
            }
        }

        return array_merge(array_values($byTag), $untagged);
    }

    /**
     * Sort items by order.
     */
    private function sort(): void
    {
        $sorter = fn ($a, $b) => $a->order <=> $b->order;

        usort($this->items, $sorter);

        uasort($this->taggedItems, $sorter);

        // Sort children recursively
        $this->taggedItems = array_map(
            fn ($item) => $this->sortChildren($item),
            $this->taggedItems
        );

        $this->items = array_map(
            fn ($item) => $this->sortChildren($item),
            $this->items
        );

        $this->sorted = true;
    }

    /**
     * Sort children recursively.
     */
    private function sortChildren(MenuItem $item): MenuItem
    {
        if (empty($item->children)) {
            return $item;
        }

        $children = $item->children;
        usort($children, fn ($a, $b) => $a->order <=> $b->order);
        $children = array_map(fn ($c) => $this->sortChildren($c), $children);

        return $item->withChildren($children);
    }
}
