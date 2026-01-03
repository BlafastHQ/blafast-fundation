<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Resources;

use Blafast\Foundation\Dto\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * JSON:API resource for menu items.
 *
 * Formats menu items according to JSON:API specification with
 * support for hierarchical relationships.
 *
 * @property MenuItem $resource
 */
class MenuItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return [
            'type' => 'menu-item',
            'id' => $this->getId($item),
            'attributes' => [
                'label' => $item->label,
                'icon' => $item->icon,
                'route' => $item->route,
                'url' => $item->url ?? $this->resolveUrl($item),
                'order' => $item->order,
            ],
            'relationships' => $this->when(
                ! empty($item->children),
                fn () => [
                    'children' => [
                        'data' => collect($item->children)
                            ->map(fn ($c) => [
                                'type' => 'menu-item',
                                'id' => $this->getId($c),
                            ])
                            ->values()
                            ->all(),
                    ],
                ]
            ),
        ];
    }

    /**
     * Get unique identifier for menu item.
     */
    protected function getId(MenuItem $item): string
    {
        return $item->tag ?? Str::slug($item->label);
    }

    /**
     * Resolve route to URL.
     */
    protected function resolveUrl(MenuItem $item): ?string
    {
        if ($item->route) {
            try {
                return route($item->route);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
