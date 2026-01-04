<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Filters\DateRangeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Service for building queries with Spatie Query Builder.
 *
 * Integrates with ExposesApiStructure trait to automatically configure
 * filters, sorts, and includes based on field metadata.
 */
class QueryBuilderService
{
    /**
     * Build a QueryBuilder instance configured for the given model.
     *
     * @param  class-string<HasApiStructure&\Illuminate\Database\Eloquent\Model>  $modelClass
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function buildQuery(string $modelClass, Request $request): Builder
    {
        /** @phpstan-ignore staticMethod.notFound */
        $structure = $modelClass::getApiStructure();

        $query = QueryBuilder::for($modelClass, $request)
            ->allowedFilters($this->buildFilters($modelClass, $structure))
            ->allowedSorts($this->buildSorts($structure))
            /** @phpstan-ignore staticMethod.notFound */
            ->allowedIncludes($modelClass::getApiIncludes());

        // Apply search if present
        $search = $request->input('search');
        if ($search !== null && $search !== '') {
            /** @phpstan-ignore argument.type */
            $this->applySearch($query, (string) $search, $modelClass, $structure);
        }

        return $query->getEloquentBuilder();
    }

    /**
     * Build allowed filters from API structure.
     *
     * Creates appropriate filter types based on field metadata:
     * - String fields: partial match
     * - UUID, Integer, Boolean: exact match
     * - Date/DateTime: custom date range filter
     * - Relations: exact match on relation field
     *
     * @param  class-string<HasApiStructure>  $modelClass
     * @param  array<string, mixed>  $structure
     * @return array<int, AllowedFilter>
     */
    protected function buildFilters(string $modelClass, array $structure): array
    {
        $filters = [];

        foreach ($structure['fields'] as $field) {
            if (! ($field['filterable'] ?? false)) {
                continue;
            }

            $filter = $this->createFilterForField($field);
            if ($filter !== null) {
                $filters[] = $filter;
            }
        }

        // Add custom filters if defined in the structure
        foreach ($structure['filters'] ?? [] as $filterDef) {
            if (is_string($filterDef)) {
                // Simple string filter - use exact match
                $filters[] = AllowedFilter::exact($filterDef);
            } elseif (is_array($filterDef) && isset($filterDef['name'])) {
                // Custom filter definition
                $filters[] = $this->createCustomFilter($filterDef);
            }
        }

        return $filters;
    }

    /**
     * Create an appropriate filter for a field based on its type.
     *
     * @param  array<string, mixed>  $field
     */
    protected function createFilterForField(array $field): ?AllowedFilter
    {
        $name = $field['name'];
        $type = $field['type'] ?? 'string';

        return match (true) {
            // String fields use partial (LIKE/ILIKE) matching
            str_starts_with($type, 'string') => AllowedFilter::partial($name),

            // UUID and ID fields need exact matching
            str_starts_with($type, 'uuid') => AllowedFilter::exact($name),
            $name === 'id' => AllowedFilter::exact($name),

            // Numeric fields use exact matching
            str_starts_with($type, 'integer'),
            str_starts_with($type, 'decimal'),
            str_starts_with($type, 'float') => AllowedFilter::exact($name),

            // Boolean fields use exact matching
            str_starts_with($type, 'boolean') => AllowedFilter::exact($name),

            // Date fields use custom date range filter
            str_starts_with($type, 'date'),
            str_starts_with($type, 'datetime') => AllowedFilter::custom($name, new DateRangeFilter),

            // Relation fields - filter by related model's field
            str_starts_with($type, 'relation') => $this->createRelationFilter($field),

            // Default to exact match for unknown types
            default => AllowedFilter::exact($name),
        };
    }

    /**
     * Create a filter for relation fields.
     *
     * @param  array<string, mixed>  $field
     */
    protected function createRelationFilter(array $field): ?AllowedFilter
    {
        $relationName = $field['relation_name'] ?? null;
        $relationField = $field['relation_field'] ?? 'id';

        if ($relationName === null) {
            return null;
        }

        // Create filter like "category.name" or "user.id"
        return AllowedFilter::exact("{$relationName}.{$relationField}");
    }

    /**
     * Create a custom filter from definition.
     *
     * @param  array<string, mixed>  $filterDef
     */
    protected function createCustomFilter(array $filterDef): AllowedFilter
    {
        $name = $filterDef['name'];
        $type = $filterDef['type'] ?? 'exact';

        return match ($type) {
            'partial' => AllowedFilter::partial($name),
            'scope' => AllowedFilter::scope($name),
            'callback' => AllowedFilter::callback($name, $filterDef['callback']),
            default => AllowedFilter::exact($name),
        };
    }

    /**
     * Build allowed sorts from API structure.
     *
     * @param  array<string, mixed>  $structure
     * @return array<int, AllowedSort|string>
     */
    protected function buildSorts(array $structure): array
    {
        $sorts = [];
        $addedSortNames = [];

        // Add sorts from fields marked as sortable
        foreach ($structure['fields'] as $field) {
            if ($field['sortable'] ?? false) {
                $sorts[] = AllowedSort::field($field['name']);
                $addedSortNames[] = $field['name'];
            }
        }

        // Add custom sorts if defined
        foreach ($structure['sorts'] ?? [] as $sort) {
            $sortName = is_string($sort) ? ltrim($sort, '-') : ($sort['name'] ?? '');

            // Skip if already added from fields
            if (in_array($sortName, $addedSortNames, true)) {
                continue;
            }

            if (is_string($sort)) {
                $sorts[] = AllowedSort::field(ltrim($sort, '-'));
                $addedSortNames[] = ltrim($sort, '-');
            } elseif (is_array($sort) && isset($sort['name'])) {
                // Custom sort implementation would go here
                $sorts[] = AllowedSort::field($sort['name']);
                $addedSortNames[] = $sort['name'];
            }
        }

        return $sorts;
    }

    /**
     * Apply search to the query.
     *
     * Supports two strategies:
     * - 'like': ILIKE search across fields (default, works on PostgreSQL and MySQL)
     * - 'full_text': PostgreSQL full-text search
     *
     * @param  QueryBuilder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  class-string<HasApiStructure>  $modelClass
     * @param  array<string, mixed>  $structure
     */
    protected function applySearch(
        QueryBuilder $query,
        string $search,
        string $modelClass,
        array $structure
    ): void {
        $searchConfig = $structure['search'] ?? ['strategy' => 'like', 'fields' => []];
        /** @phpstan-ignore staticMethod.notFound */
        $fields = $searchConfig['fields'] ?: $modelClass::getApiSearchableFields();
        $strategy = $searchConfig['strategy'] ?? 'like';

        if (empty($fields)) {
            return;
        }

        $search = trim($search);
        if (empty($search)) {
            return;
        }

        $eloquent = $query->getEloquentBuilder();

        if ($strategy === 'full_text') {
            $this->applyFullTextSearch($eloquent, $search, $fields);
        } else {
            $this->applyLikeSearch($eloquent, $search, $fields);
        }
    }

    /**
     * Apply ILIKE/LIKE search across multiple fields.
     *
     * Uses ILIKE on PostgreSQL for case-insensitive search, LIKE elsewhere.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string>  $fields
     */
    protected function applyLikeSearch(Builder $query, string $search, array $fields): void
    {
        // Use ILIKE for PostgreSQL, LIKE for others (case-insensitive in SQLite)
        /** @var \Illuminate\Database\Connection $connection */
        $connection = $query->getConnection();
        $operator = $connection->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        $query->where(function ($q) use ($search, $fields, $operator) {
            foreach ($fields as $field) {
                // Handle relation fields (e.g., "category.name")
                if (str_contains($field, '.')) {
                    [$relation, $column] = explode('.', $field, 2);
                    $q->orWhereHas($relation, function ($rq) use ($column, $search, $operator) {
                        $rq->where($column, $operator, "%{$search}%");
                    });
                } else {
                    $q->orWhere($field, $operator, "%{$search}%");
                }
            }
        });
    }

    /**
     * Apply PostgreSQL full-text search.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string>  $fields
     */
    protected function applyFullTextSearch(Builder $query, string $search, array $fields): void
    {
        // Filter out relation fields - full-text search only works on local columns
        $localFields = array_filter($fields, fn ($field) => ! str_contains($field, '.'));

        if (empty($localFields)) {
            // Fall back to LIKE search if no local fields
            $this->applyLikeSearch($query, $search, $fields);

            return;
        }

        // PostgreSQL full-text search using to_tsvector and plainto_tsquery
        $columns = implode(", ', ', ", $localFields);
        $query->whereRaw(
            "to_tsvector('english', concat_ws(' ', {$columns})) @@ plainto_tsquery('english', ?)",
            [$search]
        );
    }
}
