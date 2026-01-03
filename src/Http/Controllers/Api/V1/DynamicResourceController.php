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

        // Build query with filters, sorts, includes, and search
        /** @phpstan-ignore argument.type */
        $query = $this->queryBuilder->buildQuery($modelClass, $request);

        $paginator = $this->pagination->paginate(
            $query,
            $request,
            /** @phpstan-ignore staticMethod.notFound */
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
        /** @var class-string<\Blafast\Foundation\Contracts\HasApiStructure&\Illuminate\Database\Eloquent\Model> $modelClass */
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
        /** @var class-string<\Blafast\Foundation\Contracts\HasApiStructure&\Illuminate\Database\Eloquent\Model> $modelClass */
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
        /** @var class-string<\Blafast\Foundation\Contracts\HasApiStructure&\Illuminate\Database\Eloquent\Model> $modelClass */
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
        /** @var class-string<Model> $modelClass */
        $query = $modelClass::query();

        // Apply includes if requested
        if ($request !== null) {
            $includes = $request->input('include', '');
            if ($includes !== '' && $includes !== null) {
                /** @phpstan-ignore staticMethod.notFound */
                $allowed = $modelClass::getApiIncludes();
                $requested = explode(',', (string) $includes);
                $valid = array_intersect($requested, $allowed);
                if (! empty($valid)) {
                    $query->with($valid);
                }
            }
        }

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

    /**
     * Validate that a collection exists in the model's API structure.
     *
     * @param  class-string<HasApiStructure>  $modelClass
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function validateCollection(string $modelClass, string $collection): void
    {
        /** @phpstan-ignore staticMethod.notFound */
        $structure = $modelClass::getApiStructure();
        $collections = $structure['media_collections'] ?? [];

        if (! isset($collections[$collection])) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(
                "Collection '{$collection}' not found for this resource."
            );
        }
    }
}
