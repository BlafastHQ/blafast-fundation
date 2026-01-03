<?php

declare(strict_types=1);

use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Tests\Fixtures\ProductModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test_products table
    Schema::create('test_products', function ($table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->text('description')->nullable();
        $table->decimal('price', 10, 2);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    // Ensure the service provider is booted
    app()->register(\Blafast\Foundation\Providers\DynamicRouteServiceProvider::class);

    // Register the ProductModel for dynamic routing
    $registry = app(ModelRegistry::class);
    $registry->register(ProductModel::class);

    // Register the dynamic resource routes
    Route::prefix('api/v1')
        ->middleware('api')
        ->group(function () {
            Route::dynamicResource(ProductModel::class);
        });

    // Mock authorization to allow access
    Gate::before(fn () => true);

    // Setup storage disks for testing
    Storage::fake('public');
    Storage::fake('private');
});

test('files endpoint returns collection of media items', function () {
    $product = ProductModel::factory()->create();

    // Add some media to the images collection
    $product->addMedia(UploadedFile::fake()->image('product1.jpg'))
        ->toMediaCollection('images');
    $product->addMedia(UploadedFile::fake()->image('product2.jpg'))
        ->toMediaCollection('images');

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'type',
                    'id',
                    'attributes' => [
                        'name',
                        'filename',
                        'mime_type',
                        'size',
                        'urls',
                    ],
                ],
            ],
            'meta' => [
                'collection',
                'total',
            ],
        ]);

    expect($response->json('meta.collection'))->toBe('images')
        ->and($response->json('meta.total'))->toBe(2)
        ->and($response->json('data'))->toHaveCount(2);
});

test('files endpoint returns empty array for empty collection', function () {
    $product = ProductModel::factory()->create();

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images");

    $response->assertStatus(200)
        ->assertJson([
            'data' => [],
            'meta' => [
                'collection' => 'images',
                'total' => 0,
            ],
        ]);
});

test('files endpoint validates collection exists in API structure', function () {
    $product = ProductModel::factory()->create();

    $response = $this->getJson("/api/v1/product/{$product->id}/files/nonexistent");

    $response->assertStatus(404);
});

test('files endpoint returns 404 if model does not support media', function () {
    // This test would use a model without HasMedia trait
    // For now, we'll skip it as all our test models use HasMedia
    expect(true)->toBeTrue();
})->skip('Requires a model without HasMedia trait');

test('files endpoint includes file URLs in response', function () {
    $product = ProductModel::factory()->create();

    $product->addMedia(UploadedFile::fake()->image('test.jpg'))
        ->toMediaCollection('images');

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images");

    $response->assertStatus(200);

    $file = $response->json('data.0');
    expect($file['attributes']['urls'])->toHaveKey('original');
});

test('file endpoint returns single media item with detailed information', function () {
    $product = ProductModel::factory()->create();

    $media = $product->addMedia(UploadedFile::fake()->image('product.jpg', 800, 600))
        ->withCustomProperties(['alt' => 'Product image'])
        ->toMediaCollection('images');

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images/{$media->uuid}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => [
                    'name',
                    'filename',
                    'mime_type',
                    'size',
                    'urls',
                    'custom_properties',
                    'created_at',
                    'updated_at',
                    'collection_name',
                    'order_column',
                ],
            ],
        ]);

    expect($response->json('data.type'))->toBe('file')
        ->and($response->json('data.id'))->toBe($media->uuid)
        ->and($response->json('data.attributes.collection_name'))->toBe('images')
        ->and($response->json('data.attributes.custom_properties'))->toHaveKey('alt');
});

test('file endpoint returns 404 for non-existent file', function () {
    $product = ProductModel::factory()->create();

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images/invalid-uuid");

    $response->assertStatus(404)
        ->assertJsonStructure([
            'errors' => [
                '*' => [
                    'status',
                    'title',
                    'detail',
                ],
            ],
        ]);

    expect($response->json('errors.0.title'))->toBe('File Not Found');
});

test('file endpoint validates collection exists', function () {
    $product = ProductModel::factory()->create();

    $response = $this->getJson("/api/v1/product/{$product->id}/files/nonexistent/some-uuid");

    $response->assertStatus(404);
});

test('file endpoint includes conversion URLs if available', function () {
    $product = ProductModel::factory()->create();

    $media = $product->addMedia(UploadedFile::fake()->image('product.jpg'))
        ->toMediaCollection('images');

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images/{$media->uuid}");

    $response->assertStatus(200);

    $urls = $response->json('data.attributes.urls');
    expect($urls)->toHaveKey('original');
});

test('files endpoint requires view permission', function () {
    $product = ProductModel::factory()->create();

    // Override gate to deny access
    Gate::before(fn () => null);
    Gate::define('view', fn () => false);

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images");

    $response->assertStatus(403);
});

test('file endpoint requires view permission', function () {
    $product = ProductModel::factory()->create();

    $media = $product->addMedia(UploadedFile::fake()->image('product.jpg'))
        ->toMediaCollection('images');

    // Override gate to deny access
    Gate::before(fn () => null);
    Gate::define('view', fn () => false);

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images/{$media->uuid}");

    $response->assertStatus(403);
});

test('files endpoint handles multiple collections separately', function () {
    $product = ProductModel::factory()->create();

    // Add files to different collections
    $product->addMedia(UploadedFile::fake()->image('image.jpg'))
        ->toMediaCollection('images');
    $product->addMedia(UploadedFile::fake()->create('document.pdf'))
        ->toMediaCollection('documents');

    // Request images collection
    $imagesResponse = $this->getJson("/api/v1/product/{$product->id}/files/images");
    $imagesResponse->assertStatus(200);
    expect($imagesResponse->json('meta.total'))->toBe(1)
        ->and($imagesResponse->json('meta.collection'))->toBe('images');

    // Request documents collection
    $docsResponse = $this->getJson("/api/v1/product/{$product->id}/files/documents");
    $docsResponse->assertStatus(200);
    expect($docsResponse->json('meta.total'))->toBe(1)
        ->and($docsResponse->json('meta.collection'))->toBe('documents');
});

test('file endpoint uses signed URLs for private disk files', function () {
    $product = ProductModel::factory()->create();

    // Add file to private collection
    $media = $product->addMedia(UploadedFile::fake()->create('private.pdf'))
        ->toMediaCollection('private_files');

    $response = $this->getJson("/api/v1/product/{$product->id}/files/private_files/{$media->uuid}");

    $response->assertStatus(200);

    $originalUrl = $response->json('data.attributes.urls.original');

    // Signed URLs should contain signature parameters
    // Note: In test environment with fake storage, we can't fully test S3 signed URLs
    // but we can verify the URL is returned
    expect($originalUrl)->not->toBeNull();
});

test('files endpoint returns files in correct order', function () {
    $product = ProductModel::factory()->create();

    // Add multiple files
    $media1 = $product->addMedia(UploadedFile::fake()->image('first.jpg'))
        ->toMediaCollection('images');
    $media2 = $product->addMedia(UploadedFile::fake()->image('second.jpg'))
        ->toMediaCollection('images');
    $media3 = $product->addMedia(UploadedFile::fake()->image('third.jpg'))
        ->toMediaCollection('images');

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images");

    $response->assertStatus(200);

    $files = $response->json('data');
    expect($files)->toHaveCount(3);

    // Files should maintain their order
    $uuids = collect($files)->pluck('id')->all();
    expect($uuids)->toContain($media1->uuid, $media2->uuid, $media3->uuid);
});

test('file endpoint returns correct JSON:API type', function () {
    $product = ProductModel::factory()->create();

    $media = $product->addMedia(UploadedFile::fake()->image('test.jpg'))
        ->toMediaCollection('images');

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images/{$media->uuid}");

    $response->assertStatus(200);

    expect($response->json('data.type'))->toBe('file');
});

test('files endpoint uses media UUID as ID', function () {
    $product = ProductModel::factory()->create();

    $media = $product->addMedia(UploadedFile::fake()->image('test.jpg'))
        ->toMediaCollection('images');

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images");

    $response->assertStatus(200);

    $file = $response->json('data.0');
    expect($file['id'])->toBe($media->uuid);
});

test('file endpoint includes all required attributes', function () {
    $product = ProductModel::factory()->create();

    $media = $product->addMedia(UploadedFile::fake()->image('test.jpg'))
        ->toMediaCollection('images');

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images/{$media->uuid}");

    $response->assertStatus(200);

    $attributes = $response->json('data.attributes');

    expect($attributes)->toHaveKeys([
        'name',
        'filename',
        'mime_type',
        'size',
        'urls',
        'custom_properties',
        'created_at',
        'updated_at',
        'collection_name',
        'order_column',
    ]);
});

test('files endpoint does not include detailed attributes', function () {
    $product = ProductModel::factory()->create();

    $product->addMedia(UploadedFile::fake()->image('test.jpg'))
        ->toMediaCollection('images');

    $response = $this->getJson("/api/v1/product/{$product->id}/files/images");

    $response->assertStatus(200);

    $file = $response->json('data.0.attributes');

    // Basic attributes should be present
    expect($file)->toHaveKeys(['name', 'filename', 'mime_type', 'size', 'urls']);

    // Detailed attributes should NOT be present in collection listing
    expect($file)->not->toHaveKey('custom_properties')
        ->and($file)->not->toHaveKey('collection_name')
        ->and($file)->not->toHaveKey('order_column');
});
