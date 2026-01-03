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
     * @param  object  $event  Model updated event
     */
    public function handle(object $event): void
    {
        // Extract model from event
        $model = $this->extractModel($event);

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
     * @param  object  $event  Event object
     */
    protected function extractModel(object $event): ?Model
    {
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

        return null;
    }
}
