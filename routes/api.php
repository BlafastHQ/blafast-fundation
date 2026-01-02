<?php

declare(strict_types=1);

use Blafast\Foundation\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for the Blafast Foundation
| module. These routes are prefixed with /api/v1.
|
*/

// Authentication routes
Route::prefix('api/v1/auth')->group(function () {
    // Public routes (with rate limiting)
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:auth')
        ->name('auth.login');

    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::get('tokens', [AuthController::class, 'tokens'])->name('auth.tokens.index');
        Route::post('tokens', [AuthController::class, 'createToken'])->name('auth.tokens.create');
        Route::delete('tokens/{tokenId}', [AuthController::class, 'revokeToken'])->name('auth.tokens.revoke');
    });
});
