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

            $slug = $modelClass::getApiSlug();
            $controller = $options['controller'] ?? DynamicResourceController::class;

            // Secure by default (H1): the trio every built-in route in
            // routes/api.php attaches. Caller middleware APPENDS — it cannot strip
            // the defaults. Deliberately no `org.required`: that would deny the
            // superadmin global-context browsing the package supports.
            $middleware = array_values(array_unique(array_merge(
                ['auth:sanctum', 'throttle:api', 'org.resolve'],
                $options['middleware'] ?? [],
            )));

            // The macro's own meta/{slug} route is gone (L4): it was permanently
            // shadowed by the global authorized meta route under api/v1, had drifted
            // from the live implementation, and performed NO authorization — so it
            // silently became the served endpoint under any other prefix.

            // Register resource routes using array-based group syntax
            // Note: Meta endpoint is handled globally by ModelMetaController
            /** @phpstan-ignore method.notFound */
            $this->group([
                'prefix' => $slug,
                'middleware' => $middleware,
                'as' => "{$slug}.",
                'modelSlug' => $slug, // Store slug in group attributes
            ], function () use ($controller) {
                // List endpoint
                Route::get('/', [$controller, 'index'])
                    ->name('index');

                // Show endpoint
                Route::get('/{id}', [$controller, 'show'])
                    ->whereUuid('id')
                    ->name('show');

                // Files collection endpoint
                Route::get('/{id}/files/{collection}', [$controller, 'files'])
                    ->whereUuid('id')
                    ->name('files');

                // Single file endpoint
                Route::get('/{id}/files/{collection}/{file}', [$controller, 'file'])
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
