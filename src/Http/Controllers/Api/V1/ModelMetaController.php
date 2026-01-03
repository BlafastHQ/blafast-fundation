<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Controllers\Api\V1;

use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Http\Resources\ModelMetaResource;
use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\ModelMetaService;
use Blafast\Foundation\Services\ModelRegistry;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for model metadata endpoint.
 *
 * Provides the complete "identity card" of a model, including fields,
 * endpoints, available methods, and configuration for frontend dynamic rendering.
 */
class ModelMetaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ModelRegistry $registry,
        private ModelMetaService $metaService,
        private MetadataCacheService $cache,
    ) {}

    /**
     * Get metadata for a model.
     *
     * Returns complete metadata including fields, endpoints, methods,
     * and media collections for the specified model slug.
     *
     * This endpoint is publicly accessible, but the response is filtered
     * based on user permissions when authenticated.
     */
    public function __invoke(Request $request, string $modelSlug): JsonResponse
    {
        /** @var class-string<HasApiStructure> $modelClass */
        $modelClass = $this->registry->resolve($modelSlug);

        // Only check authorization if user is authenticated
        // This allows public access to meta but filters based on permissions
        if ($request->user()) {
            $this->authorize('viewAny', $modelClass);
        }

        // Cache the metadata response
        // Cache key includes user ID to handle permission-based filtering
        $userId = $request->user()?->getAuthIdentifier() ?? 'guest';
        $cacheKey = "model-meta:{$modelSlug}:user:{$userId}";

        $meta = $this->cache->remember(
            $cacheKey,
            [$modelSlug, 'model-meta'],
            fn () => $this->metaService->compile($modelClass, $request->user())
        );

        return response()->json([
            'data' => new ModelMetaResource($meta),
        ]);
    }
}
