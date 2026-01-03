<?php

declare(strict_types=1);

namespace Blafast\Foundation\Providers;

use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Http\Controllers\Api\V1\DynamicResourceController;
use Blafast\Foundation\Services\ModelRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for dynamic resource routing macros.
 */
class DynamicRouteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerDynamicResourceMacro();
        $this->registerDynamicResourcesMacro();
    }

    /**
     * Register the dynamicResource route macro.
     */
    protected function registerDynamicResourceMacro(): void
    {
        Route::macro('dynamicResource', function (string $modelClass, array $options = []) {
            $registry = app(ModelRegistry::class);
            /** @var class-string<HasApiStructure> $modelClass */
            $registry->register($modelClass);

            /** @phpstan-ignore staticMethod.notFound */
            $slug = $modelClass::getApiSlug();
            $controller = $options['controller'] ?? DynamicResourceController::class;
            $middleware = $options['middleware'] ?? [];

            // Register meta endpoint first (outside prefix for clean URL)
            /** @phpstan-ignore method.notFound */
            $this->get("meta/{slug}", [$controller, 'meta'])
                ->where('slug', $slug)
                ->name("{$slug}.meta");

            // Register resource routes using array-based group syntax
            /** @phpstan-ignore method.notFound */
            $this->group([
                'prefix' => $slug,
                'middleware' => $middleware,
                'as' => "{$slug}.",
                'modelSlug' => $slug, // Store slug in group attributes
            ], function () use ($controller) {
                // List endpoint
                \Illuminate\Support\Facades\Route::get('/', [$controller, 'index'])
                    ->name('index');

                // Show endpoint
                \Illuminate\Support\Facades\Route::get('/{id}', [$controller, 'show'])
                    ->whereUuid('id')
                    ->name('show');

                // Files collection endpoint
                \Illuminate\Support\Facades\Route::get('/{id}/files/{collection}', [$controller, 'files'])
                    ->whereUuid('id')
                    ->name('files');

                // Single file endpoint
                \Illuminate\Support\Facades\Route::get('/{id}/files/{collection}/{file}', [$controller, 'file'])
                    ->whereUuid('id')
                    ->whereUuid('file')
                    ->name('file');
            });
        });
    }

    /**
     * Register the dynamicResources route macro for bulk registration.
     */
    protected function registerDynamicResourcesMacro(): void
    {
        Route::macro('dynamicResources', function (array $models) {
            foreach ($models as $modelClass => $options) {
                if (is_int($modelClass)) {
                    // Simple array: ['Model1', 'Model2']
                    /** @phpstan-ignore method.notFound */
                    $this->dynamicResource($options);
                } else {
                    // Associative array: ['Model1' => ['middleware' => [...]]]
                    /** @phpstan-ignore method.notFound */
                    $this->dynamicResource($modelClass, $options);
                }
            }
        });
    }
}
