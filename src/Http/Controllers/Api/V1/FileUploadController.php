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
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
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

        // Task 24 (M17): enforce a MIME allow-list server-side. A collection with
        // no declared accepted_mimes gets the safe DEFAULT list — media-library
        // treats an empty list as accept-everything, which allowed .html/.svg
        // uploads served as active content from the app origin (stored XSS).
        $this->assertAcceptableMime($modelClass, $collection, $file);

        /** @var HasMedia $model */
        $media = $model->addMedia($file)
            ->usingName($request->input('name', $file->getClientOriginalName()))
            // Normalised stored filename — never trust the client's.
            ->sanitizingFileName(function (string $fileName): string {
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $name = Str::slug(pathinfo($fileName, PATHINFO_FILENAME)) ?: 'file';

                return $extension !== '' ? "{$name}.{$extension}" : $name;
            })
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

        /** @var HasMedia $model */
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

        $structure = $modelClass::getApiStructure();
        $collections = $structure['media_collections'] ?? [];

        if (! isset($collections[$collection])) {
            throw new NotFoundHttpException(
                "Collection '{$collection}' not found for this resource."
            );
        }
    }

    /**
     * The default MIME allow-list applied when a collection declares no
     * accepted_mimes (task 24/M17): safe documents and raster images only —
     * no SVG, no HTML, nothing servable as active content.
     *
     * @var array<int, string>
     */
    private const DEFAULT_ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
        'text/plain',
        'text/csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * Reject uploads whose MIME is outside the collection's allow-list (or the
     * safe default when the collection declares none).
     *
     * @param  class-string<HasApiStructure>  $modelClass
     */
    protected function assertAcceptableMime(string $modelClass, string $collection, UploadedFile $file): void
    {
        $structure = $modelClass::getApiStructure();
        $declared = $structure['media_collections'][$collection]['accepted_mimes'] ?? [];

        $allowed = $declared !== [] ? $declared : self::DEFAULT_ALLOWED_MIMES;
        $mime = (string) $file->getMimeType();

        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => ["Files of type [{$mime}] are not accepted by this collection."],
            ]);
        }
    }
}
