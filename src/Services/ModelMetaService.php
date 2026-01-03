<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Dto\ModelMeta;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\HasMedia;

/**
 * Service for compiling model metadata.
 *
 * This service builds complete metadata responses for models, including
 * fields, endpoints, methods, and media collections. Metadata is cached
 * per user and organization for performance.
 */
class ModelMetaService
{
    public function __construct(
        private OrganizationContext $context,
    ) {}

    /**
     * Compile metadata for a model class.
     *
     * @param  class-string<HasApiStructure>  $modelClass
     */
    public function compile(string $modelClass, ?Authenticatable $user = null): ModelMeta
    {
        $cacheKey = $this->getCacheKey($modelClass, $user);

        return Cache::tags($this->getCacheTags($modelClass))
            ->remember($cacheKey, 600, function () use ($modelClass, $user) {
                return $this->buildMeta($modelClass, $user);
            });
    }

    /**
     * Build metadata for a model class.
     *
     * @param  class-string<HasApiStructure>  $modelClass
     */
    private function buildMeta(string $modelClass, ?Authenticatable $user): ModelMeta
    {
        /** @phpstan-ignore staticMethod.notFound */
        $structure = $modelClass::getApiStructure();
        $slug = $structure['slug'];

        $meta = new ModelMeta(
            model: class_basename($modelClass),
            label: $structure['label'],
            slug: $slug,
        );

        // Build endpoints
        $endpoints = $this->buildEndpoints($slug, $modelClass);

        // Build fields with permission filtering
        $fields = $this->buildFields($structure['fields'] ?? [], $user);

        // Build methods if model exposes them
        $methods = [];
        if (method_exists($modelClass, 'apiMethods')) {
            $methods = $this->buildMethods($modelClass, $user);
        }

        // Include media collections
        $mediaCollections = $structure['media_collections'] ?? [];

        // Include search configuration
        $search = $structure['search'] ?? null;

        // Include pagination configuration
        $pagination = $structure['pagination'] ?? null;

        return new ModelMeta(
            model: $meta->model,
            label: $meta->label,
            slug: $meta->slug,
            endpoints: $endpoints,
            fields: $fields,
            methods: $methods,
            mediaCollections: $mediaCollections,
            search: $search,
            pagination: $pagination,
        );
    }

    /**
     * Build endpoint URLs for a model.
     *
     * @param  class-string<HasApiStructure>  $modelClass
     * @return array<string, string>
     */
    private function buildEndpoints(string $slug, string $modelClass): array
    {
        $endpoints = [
            'list' => "/api/v1/{$slug}",
            'view_entity' => "/api/v1/{$slug}/{entity}",
            'meta' => "/api/v1/meta/{$slug}",
        ];

        // Add files endpoints if model uses media library
        if ($this->usesMediaLibrary($modelClass)) {
            $endpoints['files'] = "/api/v1/{$slug}/{entity}/files/{collection}";
            $endpoints['view_file'] = "/api/v1/{$slug}/{entity}/files/{collection}/{file}";
        }

        // Add call endpoint if model exposes methods
        if (method_exists($modelClass, 'apiMethods')) {
            $endpoints['call'] = "/api/v1/{$slug}/{entity}/call/{method}";
        }

        return $endpoints;
    }

    /**
     * Check if a model uses the media library.
     *
     * @param  class-string  $modelClass
     */
    private function usesMediaLibrary(string $modelClass): bool
    {
        return in_array(HasMedia::class, class_implements($modelClass) ?: []);
    }

    /**
     * Build field metadata with permission filtering.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function buildFields(array $fields, ?Authenticatable $user): array
    {
        // For now, return all fields
        // Permission-based filtering can be added later when we have field-level permissions
        return array_map(function ($field) {
            return [
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
                'sortable' => $field['sortable'] ?? false,
                'filterable' => $field['filterable'] ?? false,
                'searchable' => $field['searchable'] ?? false,
                'required' => $field['required'] ?? false,
                'readonly' => $field['readonly'] ?? false,
            ];
        }, $fields);
    }

    /**
     * Build method metadata with permission filtering.
     *
     * @param  class-string<HasApiStructure>  $modelClass
     * @return array<int, array<string, mixed>>
     */
    private function buildMethods(string $modelClass, ?Authenticatable $user): array
    {
        /** @phpstan-ignore staticMethod.notFound */
        $apiMethods = $modelClass::apiMethods();
        /** @phpstan-ignore staticMethod.notFound */
        $slug = $modelClass::getApiSlug();
        $methods = [];

        foreach ($apiMethods as $methodSlug => $config) {
            // Check if user can execute this method
            if ($user && ! $this->canExecuteMethod($user, $slug, $methodSlug)) {
                continue;
            }

            $methods[] = [
                'slug' => $methodSlug,
                'http_method' => $config['http_method'],
                'description' => $config['description'] ?? null,
                'parameters' => $this->formatParameters($config['parameters'] ?? []),
                'returns' => $config['returns'] ?? null,
                'queued' => $config['queued'] ?? false,
            ];
        }

        return $methods;
    }

    /**
     * Format method parameters.
     *
     * @param  array<string, array<string, mixed>>  $parameters
     * @return array<int, array<string, mixed>>
     */
    private function formatParameters(array $parameters): array
    {
        $formatted = [];

        foreach ($parameters as $name => $config) {
            $formatted[] = [
                'name' => $name,
                'type' => $config['type'],
                'required' => $config['required'] ?? false,
                'default' => $config['default'] ?? null,
                'description' => $config['description'] ?? null,
            ];
        }

        return $formatted;
    }

    /**
     * Check if user can execute a method.
     */
    private function canExecuteMethod(Authenticatable $user, string $modelSlug, string $methodSlug): bool
    {
        // Check for specific method permission or general exec permission
        return $user->can("exec.{$modelSlug}")
            || $user->can("exec.{$modelSlug}.{$methodSlug}");
    }

    /**
     * Get cache key for model metadata.
     *
     * @param  class-string<HasApiStructure>  $modelClass
     */
    private function getCacheKey(string $modelClass, ?Authenticatable $user): string
    {
        /** @phpstan-ignore staticMethod.notFound */
        $slug = $modelClass::getApiSlug();

        $parts = [
            'model-meta',
            $slug,
            $this->context->id() ?? 'global',
            $user?->getAuthIdentifier() ?? 'anonymous',
        ];

        return implode(':', $parts);
    }

    /**
     * Get cache tags for model metadata.
     *
     * @param  class-string<HasApiStructure>  $modelClass
     * @return array<int, string>
     */
    private function getCacheTags(string $modelClass): array
    {
        /** @phpstan-ignore staticMethod.notFound */
        $slug = $modelClass::getApiSlug();

        $tags = [
            $slug,
            'model-meta',
        ];

        if ($orgId = $this->context->id()) {
            $tags[] = "org-{$orgId}";
        } else {
            $tags[] = 'global';
        }

        return $tags;
    }

    /**
     * Invalidate cache for a model.
     *
     * @param  class-string<HasApiStructure>  $modelClass
     */
    public function invalidate(string $modelClass): void
    {
        Cache::tags($this->getCacheTags($modelClass))->flush();
    }
}
