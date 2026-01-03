<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Contracts\HasApiStructure;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Registry for tracking models that expose API structures.
 *
 * This service maintains a mapping between model slugs and their class names,
 * enabling dynamic route registration and model resolution.
 */
class ModelRegistry
{
    /**
     * Registered models indexed by their slugs.
     *
     * @var array<string, class-string>
     */
    private array $models = [];

    /**
     * Register a model in the registry.
     *
     * @param  class-string  $modelClass
     *
     * @throws \InvalidArgumentException If model doesn't implement HasApiStructure
     */
    public function register(string $modelClass): void
    {
        if (! in_array(HasApiStructure::class, class_implements($modelClass) ?: [])) {
            throw new \InvalidArgumentException(
                "{$modelClass} must implement HasApiStructure"
            );
        }

        /** @var class-string<HasApiStructure> $modelClass */
        /** @phpstan-ignore staticMethod.notFound */
        $slug = $modelClass::getApiSlug();
        $this->models[$slug] = $modelClass;
    }

    /**
     * Get a model class by slug.
     *
     * @return class-string|null
     */
    public function get(string $slug): ?string
    {
        return $this->models[$slug] ?? null;
    }

    /**
     * Check if a model is registered for the given slug.
     */
    public function has(string $slug): bool
    {
        return isset($this->models[$slug]);
    }

    /**
     * Get all registered models.
     *
     * @return array<string, class-string>
     */
    public function all(): array
    {
        return $this->models;
    }

    /**
     * Get all registered model slugs.
     *
     * @return array<string>
     */
    public function slugs(): array
    {
        return array_keys($this->models);
    }

    /**
     * Resolve a model class by slug, throwing an exception if not found.
     *
     * @return class-string
     *
     * @throws ModelNotFoundException
     */
    public function resolve(string $slug): string
    {
        if (! $this->has($slug)) {
            throw new ModelNotFoundException("Model not found for slug: {$slug}");
        }

        return $this->models[$slug];
    }

    /**
     * Clear all registered models (useful for testing).
     */
    public function clear(): void
    {
        $this->models = [];
    }
}
