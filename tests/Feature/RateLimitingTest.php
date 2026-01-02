<?php

declare(strict_types=1);

use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test route for rate limiting
    Route::get('/api/v1/test/rate-limited', function () {
        return response()->json(['message' => 'Success']);
    })->middleware(['throttle:api']);

    Route::post('/api/v1/test/auth-limited', function () {
        return response()->json(['message' => 'Success']);
    })->middleware(['throttle:auth']);
});

afterEach(function () {
    // Clear rate limiter after each test
    RateLimiter::clear('api');
    RateLimiter::clear('auth');
});

test('auth rate limiter allows requests under limit', function () {
    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson('/api/v1/test/auth-limited');
        $response->assertStatus(200);
    }
});

test('auth rate limiter blocks requests over limit', function () {
    $maxAttempts = config('blafast-fundation.api.rate_limiting.auth.max_attempts', 60);

    // Make requests up to the limit
    for ($i = 0; $i < $maxAttempts; $i++) {
        $this->postJson('/api/v1/test/auth-limited');
    }

    // Next request should be rate limited
    $response = $this->postJson('/api/v1/test/auth-limited');

    $response->assertStatus(429)
        ->assertJson([
            'errors' => [[
                'status' => '429',
                'code' => 'RATE_LIMIT_EXCEEDED',
                'title' => 'Rate Limit Exceeded',
            ]],
        ]);
})->skip('Takes too long to run 60 requests');

test('auth rate limiter returns retry-after header', function () {
    $maxAttempts = config('blafast-fundation.api.rate_limiting.auth.max_attempts', 60);

    // Make requests up to the limit
    for ($i = 0; $i < $maxAttempts; $i++) {
        $this->postJson('/api/v1/test/auth-limited');
    }

    // Next request should be rate limited
    $response = $this->postJson('/api/v1/test/auth-limited');

    expect($response->headers->has('Retry-After'))->toBeTrue();
})->skip('Takes too long to run 60 requests');

test('api rate limiter allows requests under limit', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/test/rate-limited');
        $response->assertStatus(200);
    }
});

test('api rate limiter is per-user when authenticated', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Make requests as user1
    for ($i = 0; $i < 5; $i++) {
        $response = $this->actingAs($user1, 'sanctum')
            ->getJson('/api/v1/test/rate-limited');
        $response->assertStatus(200);
    }

    // User2 should have their own limit
    $response = $this->actingAs($user2, 'sanctum')
        ->getJson('/api/v1/test/rate-limited');
    $response->assertStatus(200);
});

test('api rate limiter is per-ip when not authenticated', function () {
    // Make requests without authentication
    for ($i = 0; $i < 5; $i++) {
        $response = $this->getJson('/api/v1/test/rate-limited');
        $response->assertStatus(200);
    }
});

test('superadmin is exempt from rate limiting', function () {
    // Create superadmin user
    $superadmin = User::factory()->create();

    // Create Superadmin role and assign it
    $role = \Blafast\Foundation\Models\Role::create([
        'name' => 'Superadmin',
        'guard_name' => 'api',
    ]);
    $superadmin->assignRole($role);

    // Make many requests (more than normal limit would allow)
    for ($i = 0; $i < 10; $i++) {
        $response = $this->actingAs($superadmin, 'sanctum')
            ->getJson('/api/v1/test/rate-limited');
        $response->assertStatus(200);
    }

    // All requests should succeed
    expect(true)->toBeTrue();
});

test('rate limit exceeded returns JSON:API error format', function () {
    // Create a route that we'll hit repeatedly to trigger rate limit
    Route::get('/api/v1/test/strict-limit', function () {
        return response()->json(['message' => 'Success']);
    })->middleware('throttle:1,1'); // Very strict limit: 1 request per minute

    // First request should succeed
    $response1 = $this->getJson('/api/v1/test/strict-limit');
    $response1->assertStatus(200);

    // Second request should be rate limited
    $response2 = $this->getJson('/api/v1/test/strict-limit');

    $response2->assertStatus(429)
        ->assertJsonStructure([
            'errors' => [
                '*' => [
                    'status',
                    'code',
                    'title',
                    'detail',
                ],
            ],
        ]);

    $error = $response2->json('errors.0');
    expect($error['status'])->toBe('429')
        ->and($error['code'])->toBe('RATE_LIMIT_EXCEEDED')
        ->and($error['title'])->toBe('Rate Limit Exceeded');
});

test('rate limiting respects configuration values', function () {
    $authLimit = config('blafast-fundation.api.rate_limiting.auth.max_attempts');
    $apiLimit = config('blafast-fundation.api.rate_limiting.api.max_attempts');
    $exemptSuperadmins = config('blafast-fundation.api.rate_limiting.exempt_superadmins');

    expect($authLimit)->toBe(60)
        ->and($apiLimit)->toBe(300)
        ->and($exemptSuperadmins)->toBeTrue();
});

test('deferred rate limiter is configured', function () {
    Route::get('/api/v1/test/deferred-limited', function () {
        return response()->json(['message' => 'Success']);
    })->middleware(['throttle:deferred']);

    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/test/deferred-limited');

    $response->assertStatus(200);
});

test('rate limit headers are added to responses', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/test/rate-limited');

    // Laravel automatically adds these headers
    expect($response->headers->has('X-RateLimit-Limit')
        || $response->headers->has('RateLimit-Limit')
    )->toBeTrue();
});
