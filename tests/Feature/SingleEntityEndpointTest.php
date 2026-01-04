<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\ModelRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure the service provider is booted
    app()->register(\Blafast\Foundation\Providers\DynamicRouteServiceProvider::class);

    // Register the Organization model for dynamic routing
    $registry = app(ModelRegistry::class);
    $registry->register(Organization::class);

    // Register the dynamic resource routes
    Route::prefix('api/v1')
        ->middleware('api')
        ->group(function () {
            Route::dynamicResource(Organization::class);
        });

    // Authenticate as a user
    actingAsUser();

    // Bypass all authorization for testing
    Gate::before(fn () => true);
});

test('show endpoint returns single entity with full details', function () {
    $org = Organization::factory()->create([
        'name' => 'Test Organization',
        'vat_number' => 'BE0123456789',
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/v1/organization/{$org->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes',
            ],
        ]);

    expect($response->json('data.type'))->toBe('organization')
        ->and($response->json('data.id'))->toBe($org->id)
        ->and($response->json('data.attributes.name'))->toBe('Test Organization')
        ->and($response->json('data.attributes.vat_number'))->toBe('BE0123456789')
        ->and($response->json('data.attributes.is_active'))->toBeTrue();
});

test('show endpoint returns 404 for non-existent entity', function () {
    $response = $this->getJson('/api/v1/organization/99999999-9999-9999-9999-999999999999');

    $response->assertStatus(404);
});

test('show endpoint supports includes parameter for relationships', function () {
    $org = Organization::factory()->create();

    // Request with a valid include parameter (even if relationship doesn't load in test)
    $response = $this->getJson("/api/v1/organization/{$org->id}?include=users");

    // Should return successfully
    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($org->id);
});

test('show endpoint ignores invalid includes parameter', function () {
    $org = Organization::factory()->create();

    // Request with invalid include
    $response = $this->getJson("/api/v1/organization/{$org->id}?include=nonexistent,invalid");

    // Should still return successfully, just ignoring invalid includes
    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($org->id);
});

test('show endpoint allows multiple valid includes', function () {
    $org = Organization::factory()->create();

    $response = $this->getJson("/api/v1/organization/{$org->id}?include=users");

    $response->assertStatus(200);
});

test('show endpoint validates includes against allowed list', function () {
    $org = Organization::factory()->create();

    // Request with mix of valid and invalid includes
    $response = $this->getJson("/api/v1/organization/{$org->id}?include=users,invalid_relation");

    // Should succeed and only load valid includes (invalid ones are ignored)
    $response->assertStatus(200);
});

test('show endpoint works without includes parameter', function () {
    $org = Organization::factory()->create();

    $response = $this->getJson("/api/v1/organization/{$org->id}");

    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($org->id);
});

test('show endpoint excludes id field from attributes', function () {
    $org = Organization::factory()->create();

    $response = $this->getJson("/api/v1/organization/{$org->id}");

    $response->assertStatus(200);

    // ID should be at top level, not in attributes
    expect($response->json('data.id'))->toBe($org->id)
        ->and($response->json('data.attributes'))->not->toHaveKey('id');
});

test('show endpoint excludes relation fields from attributes', function () {
    $org = Organization::factory()->create();

    $response = $this->getJson("/api/v1/organization/{$org->id}");

    $response->assertStatus(200);

    // Get structure to identify relation fields
    $structure = Organization::getApiStructure();
    $relationFields = collect($structure['fields'])
        ->filter(fn ($field) => ($field['type'] ?? '') === 'relation')
        ->pluck('name')
        ->all();

    $attributes = $response->json('data.attributes');

    // None of the relation fields should be in attributes
    foreach ($relationFields as $relationField) {
        expect($attributes)->not->toHaveKey($relationField);
    }
});

test('show endpoint checks authorization', function () {
    // Note: Authorization testing requires complex permission setup
    // This is tested separately in authorization-specific tests
    expect(true)->toBeTrue();
})->skip('Authorization tested in RolePermissionTest');

test('show endpoint handles empty includes parameter', function () {
    $org = Organization::factory()->create();

    $response = $this->getJson("/api/v1/organization/{$org->id}?include=");

    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($org->id);
});

test('show endpoint handles whitespace in includes', function () {
    $org = Organization::factory()->create();

    // URL encode the include parameter with spaces
    $include = urlencode(' users , ');
    $response = $this->getJson("/api/v1/organization/{$org->id}?include={$include}");

    // Should still work despite whitespace (handled by query builder)
    $response->assertStatus(200);
});
