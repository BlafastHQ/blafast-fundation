<?php

declare(strict_types=1);

use Blafast\Foundation\Http\Controllers\Api\V1\ActivityLogController;
use Blafast\Foundation\Http\Controllers\Api\V1\AuthController;
use Blafast\Foundation\Http\Controllers\Api\V1\FileUploadController;
use Blafast\Foundation\Http\Controllers\Api\V1\MenuController;
use Blafast\Foundation\Http\Controllers\Api\V1\ModelMetaController;
use Blafast\Foundation\Http\Controllers\Api\V1\ModelMethodController;
use Blafast\Foundation\Http\Controllers\Api\V1\NotificationController;
use Blafast\Foundation\Http\Controllers\Api\V1\ScheduleController;
use Blafast\Foundation\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;
use LaravelJsonApi\Laravel\Facades\JsonApiRoute;
use LaravelJsonApi\Laravel\Routing\ResourceRegistrar;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for the Blafast Foundation
| module. All API routes are versioned (v1, v2, etc.).
|
*/

/*
|--------------------------------------------------------------------------
| API Version 1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1')->name('api.v1.')->group(function () {
    // Authentication routes (non-JSON:API) - with auth rate limiting
    Route::prefix('auth')
        ->name('auth.')
        ->middleware('throttle:auth')
        ->group(function () {
            // Public routes
            Route::post('login', [AuthController::class, 'login'])->name('login');

            // Protected routes (require authentication)
            Route::middleware('auth:sanctum')->group(function () {
                Route::post('logout', [AuthController::class, 'logout'])->name('logout');
                Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
                Route::get('me', [AuthController::class, 'me'])->name('me');
                Route::get('tokens', [AuthController::class, 'tokens'])->name('tokens.index');
                Route::post('tokens', [AuthController::class, 'createToken'])->name('tokens.create');
                Route::delete('tokens/{tokenId}', [AuthController::class, 'revokeToken'])->name('tokens.revoke');
            });
        });

    // Model metadata endpoint - public but requires viewAny permission
    Route::get('meta/{modelSlug}', ModelMetaController::class)
        ->middleware('throttle:api')
        ->name('meta.show');

    // User menu endpoint - requires authentication
    Route::get('user-menu', MenuController::class)
        ->middleware(['auth:sanctum', 'throttle:api', 'org.resolve'])
        ->name('user-menu');

    // Activity log endpoints - requires authentication and permissions
    Route::middleware(['auth:sanctum', 'throttle:api', 'org.resolve'])->prefix('activities')->name('activities.')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->name('index');
        Route::get('{id}', [ActivityLogController::class, 'show'])->name('show');
    });

    // Notification endpoints - requires authentication
    Route::middleware(['auth:sanctum', 'throttle:api', 'org.resolve'])->prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
        Route::get('{id}', [NotificationController::class, 'show'])->name('show');
        Route::post('{id}/mark-read', [NotificationController::class, 'markAsRead'])->name('mark-read');
    });

    // Settings endpoints - requires authentication
    Route::middleware(['auth:sanctum', 'throttle:api', 'org.resolve'])->prefix('settings')->name('settings.')->group(function () {
        // System settings (Superadmin only)
        Route::prefix('system')->name('system.')->group(function () {
            Route::get('/', [SettingsController::class, 'systemIndex'])->name('index');
            Route::put('{key}', [SettingsController::class, 'systemUpdate'])->name('update');
            Route::delete('{key}', [SettingsController::class, 'systemDelete'])->name('delete');
        });

        // Organization settings (Admin only)
        Route::prefix('organization')->name('organization.')->group(function () {
            Route::get('/', [SettingsController::class, 'organizationIndex'])->name('index');
            Route::put('/', [SettingsController::class, 'organizationUpdate'])->name('update');
        });

        // Resolved settings (current context view)
        Route::get('resolved', [SettingsController::class, 'resolved'])->name('resolved');
    });

    // Scheduler status endpoint - requires authentication (Superadmin only)
    Route::get('scheduler/status', [ScheduleController::class, 'status'])
        ->middleware(['auth:sanctum', 'throttle:api'])
        ->name('scheduler.status');

    // File upload and management routes - requires authentication
    Route::middleware(['auth:sanctum', 'throttle:api', 'org.resolve'])->group(function () {
        Route::post('{modelSlug}/{id}/files/{collection}', [FileUploadController::class, 'store'])
            ->name('files.upload');
        Route::delete('{modelSlug}/{id}/files/{collection}/{fileId}', [FileUploadController::class, 'destroy'])
            ->name('files.delete');
    });

    // Model method execution - requires authentication
    Route::match(['get', 'post'], '{modelSlug}/{uuid}/call/{methodSlug}', ModelMethodController::class)
        ->middleware(['auth:sanctum', 'throttle:api', 'org.resolve'])
        ->whereUuid('uuid')
        ->name('model.method');

    // JSON:API resource routes - with API rate limiting
    JsonApiRoute::server('v1')
        ->prefix('api/v1')
        ->middleware('auth:sanctum')
        ->middleware('throttle:api')
        ->middleware('org.resolve')
        ->resources(function (ResourceRegistrar $server) {
            // JSON:API resources will be registered here
            // Example: $server->resource('organizations', OrganizationController::class);
        });
});

/*
|--------------------------------------------------------------------------
| API Version 2 Routes (Future)
|--------------------------------------------------------------------------
*/

// Route::prefix('api/v2')->name('api.v2.')->group(function () {
//     // V2 routes will be added here
// });
