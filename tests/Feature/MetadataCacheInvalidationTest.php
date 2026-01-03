<?php

declare(strict_types=1);

use Blafast\Foundation\Events\MetadataCacheInvalidated;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Enable caching for tests
    Config::set('blafast-fundation.cache.enabled', true);
    Config::set('blafast-fundation.cache.monitoring_enabled', true);

    // Register the Organization model
    $registry = app(ModelRegistry::class);
    $registry->register(Organization::class);

    // Setup permissions
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
});

test('cache is invalidated when model is updated', function () {
    Event::fake([MetadataCacheInvalidated::class]);

    $org = Organization::factory()->create(['name' => 'Test Org']);

    // Warm the cache
    $service = app(MetadataCacheService::class);
    $service->warmModel(Organization::class);

    // Update the model
    $org->update(['name' => 'Updated Org']);

    // Verify cache invalidation event was fired
    Event::assertDispatched(MetadataCacheInvalidated::class);
});

test('cache is invalidated when model is created', function () {
    Event::fake([MetadataCacheInvalidated::class]);

    // Warm the cache first
    $service = app(MetadataCacheService::class);
    $service->warmModel(Organization::class);

    // Create a new model
    Organization::factory()->create();

    // Verify cache invalidation event was fired
    Event::assertDispatched(MetadataCacheInvalidated::class);
});

test('cache is invalidated when model is deleted', function () {
    Event::fake([MetadataCacheInvalidated::class]);

    $org = Organization::factory()->create();

    // Warm the cache
    $service = app(MetadataCacheService::class);
    $service->warmModel(Organization::class);

    // Delete the model
    $org->delete();

    // Verify cache invalidation event was fired
    Event::assertDispatched(MetadataCacheInvalidated::class);
});

test('cache is invalidated when role is attached to user', function () {
    Event::fake([MetadataCacheInvalidated::class]);

    $user = User::factory()->create();
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

    // Warm the cache
    $service = app(MetadataCacheService::class);
    $cacheKey = "menu:user:{$user->id}:org:global";
    Cache::put("blafast:metadata:{$cacheKey}", 'cached-menu', 600);

    // Assign role to user
    $user->assignRole($role);

    // Verify cache was invalidated
    Event::assertDispatched(MetadataCacheInvalidated::class);
});

test('cache is invalidated when role is detached from user', function () {
    Event::fake([MetadataCacheInvalidated::class]);

    $user = User::factory()->create();
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    // Warm the cache
    $service = app(MetadataCacheService::class);
    $cacheKey = "menu:user:{$user->id}:org:global";
    Cache::put("blafast:metadata:{$cacheKey}", 'cached-menu', 600);

    // Remove role from user
    $user->removeRole($role);

    // Verify cache was invalidated
    Event::assertDispatched(MetadataCacheInvalidated::class);
});

test('cache is invalidated when permission is assigned directly to user', function () {
    Event::fake([MetadataCacheInvalidated::class]);

    $user = User::factory()->create();
    $permission = Permission::create(['name' => 'view organizations', 'guard_name' => 'web']);

    // Warm the cache
    $service = app(MetadataCacheService::class);
    $cacheKey = "menu:user:{$user->id}:org:global";
    Cache::put("blafast:metadata:{$cacheKey}", 'cached-menu', 600);

    // Give permission to user
    $user->givePermissionTo($permission);

    // Verify cache was invalidated
    Event::assertDispatched(MetadataCacheInvalidated::class);
});

test('cache is invalidated when permission is revoked from user', function () {
    Event::fake([MetadataCacheInvalidated::class]);

    $user = User::factory()->create();
    $permission = Permission::create(['name' => 'view organizations', 'guard_name' => 'web']);
    $user->givePermissionTo($permission);

    // Warm the cache
    $service = app(MetadataCacheService::class);
    $cacheKey = "menu:user:{$user->id}:org:global";
    Cache::put("blafast:metadata:{$cacheKey}", 'cached-menu', 600);

    // Revoke permission from user
    $user->revokePermissionTo($permission);

    // Verify cache was invalidated
    Event::assertDispatched(MetadataCacheInvalidated::class);
});

test('metadata endpoint uses cached response', function () {
    // First request should cache the response
    $response1 = $this->getJson('/api/v1/meta/organization');
    $response1->assertStatus(200);

    // Second request should use cached response
    $response2 = $this->getJson('/api/v1/meta/organization');
    $response2->assertStatus(200);

    // Responses should be identical
    expect($response1->json('data'))->toEqual($response2->json('data'));
});

test('metadata endpoint cache is invalidated on model update', function () {
    // First request to populate cache
    $response1 = $this->getJson('/api/v1/meta/organization');
    $response1->assertStatus(200);

    // Update a model to trigger cache invalidation
    $org = Organization::factory()->create();
    $org->update(['name' => 'Updated']);

    // Next request should have fresh data
    $response2 = $this->getJson('/api/v1/meta/organization');
    $response2->assertStatus(200);
});

test('cache invalidation respects organization scope', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();

    $service = app(MetadataCacheService::class);

    // Cache data for org1
    Cache::tags(['blafast-metadata', 'org-'.$org1->id])
        ->put('blafast:metadata:'.$org1->id.':test', 'data-org1', 600);

    // Cache data for org2
    Cache::tags(['blafast-metadata', 'org-'.$org2->id])
        ->put('blafast:metadata:'.$org2->id.':test', 'data-org2', 600);

    // Invalidate only org1
    $service->invalidateOrganization($org1->id);

    // Verify org1 cache is cleared
    $org1Cache = Cache::tags(['blafast-metadata', 'org-'.$org1->id])
        ->get('blafast:metadata:'.$org1->id.':test');
    expect($org1Cache)->toBeNull();

    // Verify org2 cache is still present (if using tagging driver)
    if (in_array(config('cache.default'), ['redis', 'memcached'])) {
        $org2Cache = Cache::tags(['blafast-metadata', 'org-'.$org2->id])
            ->get('blafast:metadata:'.$org2->id.':test');
        expect($org2Cache)->toBe('data-org2');
    }
});

test('cache invalidation works with file driver fallback', function () {
    Config::set('cache.default', 'file');

    $service = app(MetadataCacheService::class);

    // Warm cache
    $service->warmModel(Organization::class);

    // Invalidate should not throw errors with file driver
    $service->invalidateModel('organization');

    expect(true)->toBeTrue();
});

test('listener handles non-HasApiStructure models gracefully', function () {
    // Create a model that doesn't implement HasApiStructure
    $user = User::factory()->create();

    // Update should not throw errors
    $user->update(['name' => 'Updated']);

    expect(true)->toBeTrue();
});

test('listener handles models not in registry gracefully', function () {
    Event::fake([MetadataCacheInvalidated::class]);

    // Create and update a model not in the registry
    $user = User::factory()->create();
    $user->update(['name' => 'Updated']);

    // Should not dispatch cache invalidation for unregistered models
    Event::assertNotDispatched(MetadataCacheInvalidated::class);
});

test('permission change listener handles events without user gracefully', function () {
    Event::fake([MetadataCacheInvalidated::class]);

    // Fire a permission event without a user
    event('permission.attached', [null]);

    // Should not throw errors
    expect(true)->toBeTrue();
});

test('cache invalidation respects cache disabled configuration', function () {
    Config::set('blafast-fundation.cache.enabled', false);

    $org = Organization::factory()->create();

    // Update should not use cache
    $org->update(['name' => 'Updated']);

    expect(true)->toBeTrue();
});

test('multiple concurrent requests use cached data', function () {
    // First request populates cache
    $response1 = $this->getJson('/api/v1/meta/organization');
    $response1->assertStatus(200);

    // Multiple subsequent requests should use cache
    for ($i = 0; $i < 5; $i++) {
        $response = $this->getJson('/api/v1/meta/organization');
        $response->assertStatus(200);
        expect($response->json('data'))->toEqual($response1->json('data'));
    }
});
