<?php

declare(strict_types=1);

namespace Blafast\Foundation\Dto;

/**
 * Data Transfer Object for menu items.
 *
 * Represents a menu entry with support for hierarchical structures,
 * permissions, routing, and tag-based merging.
 */
readonly class MenuItem
{
    /**
     * Create a new menu item.
     *
     * @param  string  $label  Translation key for display
     * @param  string|null  $icon  Icon identifier
     * @param  string|null  $permission  Required permission to view
     * @param  string|null  $route  Named route
     * @param  string|null  $url  Direct URL
     * @param  int  $order  Sort order (default: 100)
     * @param  string|null  $tag  Unique tag for extending/merging
     * @param  array<MenuItem>  $children  Recursive menu items
     */
    public function __construct(
        public string $label,
        public ?string $icon = null,
        public ?string $permission = null,
        public ?string $route = null,
        public ?string $url = null,
        public int $order = 100,
        public ?string $tag = null,
        public array $children = [],
    ) {}

    /**
     * Create MenuItem from array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $children = array_map(
            fn ($child) => self::fromArray($child),
            $data['children'] ?? []
        );

        return new self(
            label: $data['label'],
            icon: $data['icon'] ?? null,
            permission: $data['permission'] ?? null,
            route: $data['route'] ?? null,
            url: $data['url'] ?? null,
            order: $data['order'] ?? 100,
            tag: $data['tag'] ?? null,
            children: $children,
        );
    }

    /**
     * Convert MenuItem to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'icon' => $this->icon,
            'permission' => $this->permission,
            'route' => $this->route,
            'url' => $this->url,
            'order' => $this->order,
            'tag' => $this->tag,
            'children' => array_map(fn ($c) => $c->toArray(), $this->children),
        ];
    }

    /**
     * Create a new instance with updated children.
     *
     * @param  array<MenuItem>  $children
     */
    public function withChildren(array $children): self
    {
        return new self(
            $this->label,
            $this->icon,
            $this->permission,
            $this->route,
            $this->url,
            $this->order,
            $this->tag,
            $children,
        );
    }
}
