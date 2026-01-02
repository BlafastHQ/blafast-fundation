<?php

declare(strict_types=1);

namespace Blafast\Foundation\Traits;

use Illuminate\Support\Str;

/**
 * Trait for exposing API structure metadata from Eloquent models.
 *
 * Models using this trait must implement the HasApiStructure interface
 * and define the apiStructure() method.
 */
trait ExposesApiStructure
{
    /**
     * Cached API structure to avoid repeated calculations.
     *
     * @var array<string, mixed>|null
     */
    protected static ?array $cachedApiStructure = null;

    /**
     * Get the compiled API structure with caching.
     *
     * @return array<string, mixed>
     */
    public static function getApiStructure(): array
    {
        if (static::$cachedApiStructure === null) {
            static::$cachedApiStructure = static::apiStructure();
        }

        return static::$cachedApiStructure;
    }

    /**
     * Get the model slug from API structure.
     */
    public static function getApiSlug(): string
    {
        return static::getApiStructure()['slug']
            ?? Str::kebab(class_basename(static::class));
    }

    /**
     * Get the model label from API structure.
     */
    public static function getApiLabel(): string
    {
        return static::getApiStructure()['label'] ?? '';
    }

    /**
     * Get all field definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getApiFields(): array
    {
        return static::getApiStructure()['fields'] ?? [];
    }

    /**
     * Get a specific field definition by name.
     *
     * @return array<string, mixed>|null
     */
    public static function getApiField(string $name): ?array
    {
        $fields = static::getApiFields();

        foreach ($fields as $field) {
            if ($field['name'] === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Get sortable fields.
     *
     * @return array<string>
     */
    public static function getApiSorts(): array
    {
        $structure = static::getApiStructure();

        // If explicitly defined, use those
        if (isset($structure['sorts'])) {
            return $structure['sorts'];
        }

        // Otherwise, derive from fields marked as sortable
        return collect($structure['fields'] ?? [])
            ->filter(fn ($field) => $field['sortable'] ?? false)
            ->pluck('name')
            ->values()
            ->toArray();
    }

    /**
     * Get filterable fields.
     *
     * @return array<string>
     */
    public static function getApiFilters(): array
    {
        $structure = static::getApiStructure();

        // If explicitly defined, use those
        if (isset($structure['filters'])) {
            return $structure['filters'];
        }

        // Otherwise, derive from fields marked as filterable
        return collect($structure['fields'] ?? [])
            ->filter(fn ($field) => $field['filterable'] ?? false)
            ->pluck('name')
            ->values()
            ->toArray();
    }

    /**
     * Get searchable fields.
     *
     * @return array<string>
     */
    public static function getApiSearchableFields(): array
    {
        $structure = static::getApiStructure();

        // If explicitly defined in search config, use those
        if (isset($structure['search']['fields'])) {
            return $structure['search']['fields'];
        }

        // Otherwise, derive from fields marked as searchable
        return collect($structure['fields'] ?? [])
            ->filter(fn ($field) => $field['searchable'] ?? false)
            ->pluck('name')
            ->values()
            ->toArray();
    }

    /**
     * Get the search strategy.
     */
    public static function getApiSearchStrategy(): string
    {
        return static::getApiStructure()['search']['strategy'] ?? 'like';
    }

    /**
     * Get allowed includes.
     *
     * @return array<string>
     */
    public static function getApiIncludes(): array
    {
        return static::getApiStructure()['allowed_includes'] ?? [];
    }

    /**
     * Get media collections configuration.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getApiMediaCollections(): array
    {
        return static::getApiStructure()['media_collections'] ?? [];
    }

    /**
     * Get a specific media collection configuration.
     *
     * @return array<string, mixed>|null
     */
    public static function getApiMediaCollection(string $name): ?array
    {
        return static::getApiMediaCollections()[$name] ?? null;
    }

    /**
     * Get pagination settings.
     *
     * @return array{default_size: int, max_size: int}
     */
    public static function getApiPagination(): array
    {
        $pagination = static::getApiStructure()['pagination'] ?? [];

        return [
            'default_size' => $pagination['default_size'] ?? 25,
            'max_size' => $pagination['max_size'] ?? 100,
        ];
    }

    /**
     * Check if a field is sortable.
     */
    public static function isApiFieldSortable(string $field): bool
    {
        return in_array($field, static::getApiSorts());
    }

    /**
     * Check if a field is filterable.
     */
    public static function isApiFieldFilterable(string $field): bool
    {
        return in_array($field, static::getApiFilters());
    }

    /**
     * Check if a field is searchable.
     */
    public static function isApiFieldSearchable(string $field): bool
    {
        return in_array($field, static::getApiSearchableFields());
    }

    /**
     * Check if a relation is allowed to be included.
     */
    public static function isApiIncludeAllowed(string $relation): bool
    {
        return in_array($relation, static::getApiIncludes());
    }

    /**
     * Clear the cached structure (useful for testing).
     */
    public static function clearApiStructureCache(): void
    {
        static::$cachedApiStructure = null;
    }
}
