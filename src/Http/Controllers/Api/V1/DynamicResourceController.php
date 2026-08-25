<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Controllers\Api\V1;

use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Services\FileService;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Services\PaginationService;
use Blafast\Foundation\Services\QueryBuilderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        protected QueryBuilderService $queryBuilder,
        protected FileService $fileService,
    ) {}

    /**
     * List all resources with filtering, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $modelSlug = $request->route()?->getAction('modelSlug') ?? throw new \RuntimeException('Model slug not found in route');
        /** @var class-string<HasApiStructure> $modelClass */
        $modelClass = $this->registry->resolve($modelSlug);

        $this->authorize('viewAny', $modelClass);

        // Build query with filters, sorts, includes, and search
        /** @phpstan-ignore argument.type */
        $query = $this->queryBuilder->buildQuery($modelClass, $request);

        $paginator = $this->pagination->paginate(
            $query,
            $request,
            $modelClass::getApiStructure()
        );

        return response()->json(
            $this->pagination->formatResponse(
                $paginator,
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
        /** @var class-string<HasApiStructure&Model> $modelClass */
        $modelClass = $this->registry->resolve($modelSlug);

        // Apply includes if requested
        $model = $this->findModel($modelClass, $id, $request);

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
        /** @var class-string<HasApiStructure&Model> $modelClass */
        $modelClass = $this->registry->resolve($modelSlug);
        $model = $this->findModel($modelClass, $id);

        $this->authorize('view', $model);

        // Validate collection exists in API structure
        $this->validateCollection($modelClass, $collection);

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
            'data' => $media->map(fn ($mediaItem) => $this->fileService->transform($mediaItem))->values()->all(),
            'meta' => [
                'collection' => $collection,
                'total' => $media->count(),
            ],
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
        /** @var class-string<HasApiStructure&Model> $modelClass */
        $modelClass = $this->registry->resolve($modelSlug);
        $model = $this->findModel($modelClass, $id);

        $this->authorize('view', $model);

        // Validate collection exists in API structure
        $this->validateCollection($modelClass, $collection);

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
            'data' => $this->fileService->transform($mediaItem, detailed: true),
        ]);
    }

    /**
     * Find a model by ID with optional includes.
     *
     * @param  class-string<HasApiStructure&Model>  $modelClass
     */
    protected function findModel(string $modelClass, string $id, ?Request $request = null): Model
    {
        // Includes go through the same spatie validation as index (task 14/H13):
        // an unknown include is a 400 on both endpoints now, not a silent no-op.
        $query = $request !== null
            ? $this->queryBuilder->buildShowQuery($modelClass, $request)
            : $modelClass::query();

        return $query->findOrFail($id);
    }

    /**
     * Transform a model to JSON:API format.
     *
     * @param  class-string<HasApiStructure>  $modelClass
     * @return array<string, mixed>
     */
    protected function transformModel(Model $model, string $modelClass): array
    {

        $structure = $modelClass::getApiStructure();

        $resource = [
            'type' => $structure['slug'],
            /** @phpstan-ignore property.notFound */
            'id' => $model->id,
            'attributes' => $this->buildAttributes($model, $structure),
        ];

        // Task 14 (H13): LOADED relations (eager-loaded via validated ?include=)
        // are serialized — before this, ?include= ran the extra queries and
        // returned a byte-identical payload.

        $relationships = $this->buildRelationships($model, $modelClass::getApiIncludes());

        if ($relationships !== []) {
            $resource['relationships'] = $relationships;
        }

        return $resource;
    }

    /**
     * Serialize the loaded relations among the allowed includes as embedded
     * resource objects: to-one → {type,id,attributes}|null, to-many → a list.
     *
     * @param  array<int, string>  $allowedIncludes
     * @return array<string, mixed>
     */
    protected function buildRelationships(Model $model, array $allowedIncludes): array
    {
        $relationships = [];

        foreach ($allowedIncludes as $name) {
            if (! $model->relationLoaded($name)) {
                continue;
            }

            $value = $model->getRelation($name);

            if ($value instanceof Collection) {
                $relationships[$name] = ['data' => $value->map(fn (Model $related) => $this->serializeRelated($related))->values()->all()];
            } else {
                $relationships[$name] = ['data' => $value instanceof Model ? $this->serializeRelated($value) : null];
            }
        }

        return $relationships;
    }

    /**
     * Serialize one related model: its own API structure when it exposes one,
     * otherwise the visible attributes.
     *
     * @return array<string, mixed>
     */
    protected function serializeRelated(Model $related): array
    {
        if ($related instanceof HasApiStructure) {
            $structure = $related::getApiStructure();

            return [
                'type' => $structure['slug'],
                'id' => $related->getKey(),
                'attributes' => $this->buildAttributes($related, $structure),
            ];
        }

        return [
            'type' => Str::kebab(class_basename($related)),
            'id' => $related->getKey(),
            'attributes' => Arr::except($related->attributesToArray(), [$related->getKeyName()]),
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
     * Validate that a collection exists in the model's API structure.
     *
     * @param  class-string<HasApiStructure>  $modelClass
     *
     * @throws NotFoundHttpException
     */
    protected function validateCollection(string $modelClass, string $collection): void
    {

        $structure = $modelClass::getApiStructure();
        $collections = $structure['media_collections'] ?? [];

        if (! isset($collections[$collection])) {
            throw new NotFoundHttpException(
                "Collection '{$collection}' not found for this resource."
            );
        }
    }
}
