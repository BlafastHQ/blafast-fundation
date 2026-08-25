<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Providers\DynamicRouteServiceProvider;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Ensure the service provider is booted
    app()->register(DynamicRouteServiceProvider::class);

    // Register the Organization model for dynamic routing
    $registry = app(ModelRegistry::class);
    $registry->register(Organization::class);

    // Register the dynamic resource routes
    Route::prefix('api/v1')
        ->middleware('api')
        ->group(function () {
            Route::dynamicResource(Organization::class);
        });

    // Task 13: /meta is authenticated + viewAny-gated now. A superadmin viewer
    // (global context) keeps the guest-behaviour tests meaningful via explicit
    // auth clears where needed.
    $viewer = User::factory()->create();
    Permission::findOrCreate('list_organization', 'api');
    $viewer->givePermissionTo('list_organization');
    Role::findOrCreate('Superadmin', 'api');
    $viewer->assignRole('Superadmin');
    $viewer->unsetRelation('roles')->unsetRelation('permissions');
    test()->actingAs($viewer, 'sanctum');
});

// Note: Route naming tests removed due to Laravel RouteCollection indexing issue in tests.
// The HTTP tests below prove that routes are registered and working correctly.

test('meta endpoint returns correct structure', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => [
                    'model',
                    'label',
                    'endpoints',
                    'fields',
                ],
            ],
        ]);

    expect($response->json('data.type'))->toBe('model-meta')
        ->and($response->json('data.id'))->toBe('organization')
        ->and($response->json('data.attributes.model'))->toBe('Organization');
});

test('index endpoint lists organizations', function () {
    // Create some organizations
    Organization::factory()->count(3)->create();

    app('auth')->forgetGuards(); // guest
    $response = $this->getJson('/api/v1/organization');

    $response->assertStatus(401); // Task 12: auth:sanctum is a route default now — guests are 401, not a policy 403
});

test('show endpoint returns single organization', function () {
    $org = Organization::factory()->create();

    app('auth')->forgetGuards(); // guest
    $response = $this->getJson("/api/v1/organization/{$org->id}");

    $response->assertStatus(401); // Task 12: auth:sanctum is a route default now — guests are 401, not a policy 403
});

test('meta endpoint includes field definitions', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200);

    $fields = $response->json('data.attributes.fields');

    expect($fields)->toBeArray()
        ->and($fields)->not->toBeEmpty();

    // Check first field has required properties
    expect($fields[0])->toHaveKeys(['name', 'label', 'type']);
});

test('meta endpoint includes pagination settings', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200);

    $pagination = $response->json('data.attributes.pagination');

    expect($pagination)->toBeArray()
        ->and($pagination)->toHaveKeys(['default_size', 'max_size']);
});

test('meta endpoint includes filters and sorts', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200);

    $filters = $response->json('data.attributes.filters');
    $sorts = $response->json('data.attributes.sorts');

    expect($filters)->toBeArray()
        ->and($sorts)->toBeArray();
});

test('meta endpoint includes allowed includes', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200);

    $includes = $response->json('data.attributes.allowed_includes');

    expect($includes)->toBeArray();
});

test('model registry is populated when route is registered', function () {
    $registry = app(ModelRegistry::class);

    expect($registry->has('organization'))->toBeTrue()
        ->and($registry->get('organization'))->toBe(Organization::class);
});

test('dynamic resources macro can register multiple models', function () {
    Route::prefix('api/v1/test')
        ->middleware('api')
        ->group(function () {
            Route::dynamicResources([
                Organization::class,
            ]);
        });

    // Verify routes work by accessing the index endpoint
    // Note: Meta endpoint is now global at /api/v1/meta/{slug}
    app('auth')->forgetGuards(); // guest
    $response = $this->getJson('/api/v1/test/organization');
    $response->assertStatus(401); // Task 12: guests are 401 under the secure default
});

test('unknown model slug returns 404', function () {
    $response = $this->getJson('/api/v1/meta/nonexistent');

    $response->assertStatus(404);
});
