<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Controllers\Api\V1;

use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Services\PaginationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Base controller for dynamically handling resource routes.
 *
 * This controller provides standard CRUD operations for any model
 * that implements the HasApiStructure interface.
 */
class DynamicResourceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ModelRegistry $registry,
        protected PaginationService $pagination,
    ) {}

    /**
     * Get metadata about a resource.
     */
    public function meta(Request $request, string $slug): JsonResponse
    {
        /** @var class-string<HasApiStructure> $modelClass */
        $modelClass = $this->registry->resolve($slug);

        /** @phpstan-ignore staticMethod.notFound */
        $structure = $modelClass::getApiStructure();

        return response()->json([
            'data' => [
                'type' => 'model-meta',
                'id' => $slug,
                'attributes' => $this->buildMetaResponse($slug, $structure),
            ],
        ]);
    }

    /**
     * List all resources with filtering, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $modelSlug = $request->route()?->getAction('modelSlug') ?? throw new \RuntimeException('Model slug not found in route');
        /** @var class-string<\Blafast\Foundation\Contracts\HasApiStructure> $modelClass */
        $modelClass = $this->registry->resolve($modelSlug);

        $this->authorize('viewAny', $modelClass);

        /** @phpstan-ignore argument.type */
        $query = $this->buildQuery($modelClass, $request);

        $paginator = $this->pagination->paginate(
            /** @phpstan-ignore argument.type */
            $query,
            $request,
            /** @phpstan-ignore staticMethod.notFound */
            $modelClass::getApiStructure()
        );

        return response()->json(
            $this->pagination->formatResponse(
                $paginator,
                /** @phpstan-ignore argument.type */
                fn ($model) => $this->transformModel($model, $modelClass)
            )
        );
    }

    /**
     * Show a single resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $modelSlug = $request->route()?->getAction('modelSlug') ?? throw new \RuntimeException('Model slug not found in route');
        /** @var class-string<\Blafast\Foundation\Contracts\HasApiStructure> $modelClass */
        $modelClass = $this->registry->resolve($modelSlug);
        $model = $this->findModel($modelClass, $id);

        $this->authorize('view', $model);

        return response()->json([
            'data' => $this->transformModel($model, $modelClass),
        ]);
    }

    /**
     * Get all files from a media collection.
     */
    public function files(
        Request $request,
        string $id,
        string $collection
    ): JsonResponse {
        $modelSlug = $request->route()?->getAction('modelSlug') ?? throw new \RuntimeException('Model slug not found in route');
        /** @var class-string<\Blafast\Foundation\Contracts\HasApiStructure> $modelClass */
        $modelClass = $this->registry->resolve($modelSlug);
        $model = $this->findModel($modelClass, $id);

        $this->authorize('view', $model);

        // Check if model uses HasMedia trait
        if (! method_exists($model, 'getMedia')) {
            return response()->json([
                'errors' => [[
                    'status' => '404',
                    'title' => 'Collection Not Found',
                    'detail' => 'Model does not support media collections',
                ]],
            ], 404);
        }

        $media = $model->getMedia($collection);

        return response()->json([
            'data' => $media->map(function ($mediaItem) {
                return [
                    'type' => 'media',
                    'id' => $mediaItem->uuid,
                    'attributes' => [
                        'name' => $mediaItem->name,
                        'file_name' => $mediaItem->file_name,
                        'mime_type' => $mediaItem->mime_type,
                        'size' => $mediaItem->size,
                        'url' => $mediaItem->getUrl(),
                    ],
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Get a single file from a media collection.
     */
    public function file(
        Request $request,
        string $id,
        string $collection,
        string $fileId
    ): JsonResponse {
        $modelSlug = $request->route()?->getAction('modelSlug') ?? throw new \RuntimeException('Model slug not found in route');
        /** @var class-string<\Blafast\Foundation\Contracts\HasApiStructure> $modelClass */
        $modelClass = $this->registry->resolve($modelSlug);
        $model = $this->findModel($modelClass, $id);

        $this->authorize('view', $model);

        // Check if model uses HasMedia trait
        if (! method_exists($model, 'getMedia')) {
            return response()->json([
                'errors' => [[
                    'status' => '404',
                    'title' => 'Collection Not Found',
                    'detail' => 'Model does not support media collections',
                ]],
            ], 404);
        }

        $mediaItem = $model->getMedia($collection)->firstWhere('uuid', $fileId);

        if (! $mediaItem) {
            return response()->json([
                'errors' => [[
                    'status' => '404',
                    'title' => 'File Not Found',
                    'detail' => 'File not found in collection',
                ]],
            ], 404);
        }

        return response()->json([
            'data' => [
                'type' => 'media',
                'id' => $mediaItem->uuid,
                'attributes' => [
                    'name' => $mediaItem->name,
                    'file_name' => $mediaItem->file_name,
                    'mime_type' => $mediaItem->mime_type,
                    'size' => $mediaItem->size,
                    'url' => $mediaItem->getUrl(),
                    'conversions' => $mediaItem->generated_conversions ?? [],
                ],
            ],
        ]);
    }

    /**
     * Build a query with filters, sorts, and includes.
     *
     * @param  class-string<HasApiStructure&\Illuminate\Database\Eloquent\Model>  $modelClass
     * @return \Spatie\QueryBuilder\QueryBuilder<\Illuminate\Database\Eloquent\Model>
     */
    protected function buildQuery(string $modelClass, Request $request): \Spatie\QueryBuilder\QueryBuilder
    {
        /** @phpstan-ignore staticMethod.notFound */
        $structure = $modelClass::getApiStructure();

        /** @phpstan-ignore argument.type, staticMethod.notFound */
        $query = QueryBuilder::for($modelClass)
            /** @phpstan-ignore staticMethod.notFound */
            ->allowedFilters($modelClass::getApiFilters())
            /** @phpstan-ignore staticMethod.notFound */
            ->allowedSorts($modelClass::getApiSorts())
            /** @phpstan-ignore staticMethod.notFound */
            ->allowedIncludes($modelClass::getApiIncludes());

        // Apply search if provided
        if ($request->has('search') && $request->input('search') !== '') {
            /** @phpstan-ignore argument.type */
            $this->applySearch($query, (string) $request->input('search'), $modelClass);
        }

        /** @phpstan-ignore return.type */
        return $query;
    }

    /**
     * Apply search to the query.
     *
     * @param  \Spatie\QueryBuilder\QueryBuilder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  class-string<HasApiStructure>  $modelClass
     */
    protected function applySearch(\Spatie\QueryBuilder\QueryBuilder $query, string $search, string $modelClass): void
    {
        /** @phpstan-ignore staticMethod.notFound */
        $fields = $modelClass::getApiSearchableFields();
        /** @phpstan-ignore staticMethod.notFound */
        $strategy = $modelClass::getApiSearchStrategy();

        if (empty($fields)) {
            return;
        }

        if ($strategy === 'full_text') {
            // PostgreSQL full-text search
            $query->whereFullText($fields, $search);
        } else {
            // ILIKE/LIKE search (works on both PostgreSQL and MySQL)
            $query->where(function ($q) use ($fields, $search) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'ILIKE', "%{$search}%");
                }
            });
        }
    }

    /**
     * Find a model by ID.
     */
    protected function findModel(string $modelClass, string $id): Model
    {
        /** @var class-string<Model> $modelClass */
        return $modelClass::findOrFail($id);
    }

    /**
     * Transform a model to JSON:API format.
     *
     * @param  class-string<HasApiStructure>  $modelClass
     * @return array<string, mixed>
     */
    protected function transformModel(Model $model, string $modelClass): array
    {
        /** @phpstan-ignore staticMethod.notFound */
        $structure = $modelClass::getApiStructure();

        return [
            'type' => $structure['slug'],
            /** @phpstan-ignore property.notFound */
            'id' => $model->id,
            'attributes' => $this->buildAttributes($model, $structure),
        ];
    }

    /**
     * Build attributes array from model based on structure.
     *
     * @param  array<string, mixed>  $structure
     * @return array<string, mixed>
     */
    protected function buildAttributes(Model $model, array $structure): array
    {
        $attributes = [];

        foreach ($structure['fields'] as $field) {
            // Skip ID field as it's already in the top level
            if ($field['name'] === 'id') {
                continue;
            }

            // Skip relation fields - they should be in relationships
            if (($field['type'] ?? '') === 'relation') {
                continue;
            }

            $attributes[$field['name']] = $model->{$field['name']};
        }

        return $attributes;
    }

    /**
     * Build meta response for a resource.
     *
     * @param  array<string, mixed>  $structure
     * @return array<string, mixed>
     */
    protected function buildMetaResponse(string $slug, array $structure): array
    {
        $modelClass = $this->registry->get($slug);

        return [
            'model' => $modelClass ? class_basename($modelClass) : $slug,
            'label' => $structure['label'],
            'endpoints' => [
                'list' => "/api/v1/{$slug}",
                'view_entity' => "/api/v1/{$slug}/{entity}",
                'files' => "/api/v1/{$slug}/{entity}/files/{collection}",
                'view_file' => "/api/v1/{$slug}/{entity}/files/{collection}/{file}",
                'meta' => "/api/v1/meta/{$slug}",
            ],
            'fields' => $structure['fields'],
            'filters' => $structure['filters'] ?? [],
            'sorts' => $structure['sorts'] ?? [],
            'allowed_includes' => $structure['allowed_includes'] ?? [],
            'search' => $structure['search'] ?? null,
            'pagination' => $structure['pagination'] ?? null,
        ];
    }
}
