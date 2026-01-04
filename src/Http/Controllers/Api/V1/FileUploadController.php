<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Controllers\Api\V1;

use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Http\Requests\FileUploadRequest;
use Blafast\Foundation\Services\FileService;
use Blafast\Foundation\Services\ModelRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for handling file uploads and deletions.
 *
 * Provides endpoints to upload and delete files for any model
 * that uses the HasMediaCollections trait.
 */
class FileUploadController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ModelRegistry $registry,
        protected FileService $fileService,
    ) {}

    /**
     * Upload a file to a model's media collection.
     *
     * POST /api/v1/{model-slug}/{id}/files/{collection}
     *
     * @param  string  $modelSlug  Model slug (e.g., 'product')
     * @param  string  $id  Model ID
     * @param  string  $collection  Collection name (e.g., 'images')
     */
    public function store(
        FileUploadRequest $request,
        string $modelSlug,
        string $id,
        string $collection
    ): JsonResponse {
        /** @var class-string<HasApiStructure&Model> $modelClass */
        $modelClass = $this->registry->resolve($modelSlug);
        $model = $modelClass::findOrFail($id);

        $this->authorize('update', $model);
        $this->validateCollection($modelClass, $collection);

        $file = $request->file('file');

        if ($file === null) {
            throw new \InvalidArgumentException('No file uploaded');
        }

        /** @var \Spatie\MediaLibrary\HasMedia $model */
        $media = $model->addMedia($file)
            ->usingName($request->input('name', $file->getClientOriginalName()))
            ->withCustomProperties($request->input('properties', []))
            ->toMediaCollection($collection);

        return response()->json([
            'data' => $this->fileService->transform($media, true),
        ], 201);
    }

    /**
     * Delete a file from a model's media collection.
     *
     * DELETE /api/v1/{model-slug}/{id}/files/{collection}/{fileId}
     *
     * @param  string  $modelSlug  Model slug (e.g., 'product')
     * @param  string  $id  Model ID
     * @param  string  $collection  Collection name (e.g., 'images')
     * @param  string  $fileId  File UUID
     */
    public function destroy(
        Request $request,
        string $modelSlug,
        string $id,
        string $collection,
        string $fileId
    ): JsonResponse {
        /** @var class-string<HasApiStructure&Model> $modelClass */
        $modelClass = $this->registry->resolve($modelSlug);
        $model = $modelClass::findOrFail($id);

        $this->authorize('update', $model);

        /** @var \Spatie\MediaLibrary\HasMedia $model */
        $media = $model->getMedia($collection)->firstWhere('uuid', $fileId);

        if (! $media) {
            throw new NotFoundHttpException('File not found.');
        }

        $media->delete();

        return response()->json(null, 204);
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
        /** @phpstan-ignore staticMethod.notFound */
        $structure = $modelClass::getApiStructure();
        $collections = $structure['media_collections'] ?? [];

        if (! isset($collections[$collection])) {
            throw new NotFoundHttpException(
                "Collection '{$collection}' not found for this resource."
            );
        }
    }
}
