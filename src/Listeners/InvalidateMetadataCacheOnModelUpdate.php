<?php

declare(strict_types=1);

namespace Blafast\Foundation\Listeners;

use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\ModelRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * Listener to invalidate metadata cache when models are updated.
 *
 * Listens to model update events and invalidates the relevant
 * metadata cache entries.
 */
class InvalidateMetadataCacheOnModelUpdate
{
    public function __construct(
        private MetadataCacheService $cache,
        private ModelRegistry $registry,
    ) {}

    /**
     * Handle the model updated event.
     *
     * @param  string|object  $event  Event name (for wildcard listeners) or event object
     * @param  array<mixed>|null  $data  Event data (for wildcard listeners)
     */
    public function handle(string|object $event, ?array $data = null): void
    {
        // Extract model from event
        $model = $this->extractModel($event, $data);

        if (! $model) {
            return;
        }

        // Check if model has API structure
        if (! $model instanceof HasApiStructure) {
            return;
        }

        // Get model slug from registry
        $slug = $this->registry->getSlug($model::class);

        if ($slug) {
            $this->cache->invalidateModel($slug);
        }
    }

    /**
     * Extract model from various event types.
     *
     * @param  string|object  $event  Event name or event object
     * @param  array<mixed>|null  $data  Event data (for wildcard listeners)
     */
    protected function extractModel(string|object $event, ?array $data = null): ?Model
    {
        // For wildcard listeners (Laravel 12+), event is a string and data is an array
        if (is_string($event) && is_array($data)) {
            // Data array typically contains the model as first element
            $model = $data[0] ?? null;
            if ($model instanceof Model) {
                return $model;
            }
        }

        // For regular event objects
        if (is_object($event)) {
            // Try common event property names
            if (property_exists($event, 'model') && $event->model instanceof Model) {
                return $event->model;
            }

            if (property_exists($event, 'entity') && $event->entity instanceof Model) {
                return $event->entity;
            }

            // Check if event itself is a model
            if ($event instanceof Model) {
                return $event;
            }
        }

        return null;
    }
}
