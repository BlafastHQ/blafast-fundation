<?php

declare(strict_types=1);

use Blafast\Foundation\Database\Seeders\PermissionSeeder;
use Blafast\Foundation\Database\Seeders\RoleSeeder;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions
    (new RoleSeeder)->run();
    (new PermissionSeeder)->run();

    // Ensure the service provider is booted
    app()->register(\Blafast\Foundation\Providers\DynamicRouteServiceProvider::class);

    // Register Organization model for testing
    $registry = app(ModelRegistry::class);
    $registry->register(Organization::class);

    // Register the dynamic resource routes
    Route::prefix('api/v1')
        ->middleware('api')
        ->group(function () {
            Route::dynamicResource(Organization::class);
        });

    // Create a test user with superadmin role (bypasses authorization)
    $this->user = User::factory()->create();

    // Get Superadmin role
    $superadminRole = \Blafast\Foundation\Models\Role::where('name', 'Superadmin')
        ->where('guard_name', 'api')
        ->first();

    $this->user->assignRole($superadminRole);
});

test('list endpoint returns paginated results', function () {
    Organization::factory()->count(5)->create();

    $response = actingAs($this->user)
        ->getJson('/api/v1/organization');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'type',
                    'id',
                    'attributes',
                ],
            ],
            'links' => [
                'first',
                'prev',
                'next',
            ],
            'meta' => [
                'page' => [
                    'per_page',
                    'has_more',
                ],
            ],
        ])
        ->assertJsonCount(5, 'data');
});

test('list endpoint supports partial filtering on string fields', function () {
    Organization::factory()->create(['name' => 'Acme Corporation']);
    Organization::factory()->create(['name' => 'Test Company']);
    Organization::factory()->create(['name' => 'Another Corp']);

    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?filter[name]=Acme');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.name', 'Acme Corporation');
});

test('list endpoint supports exact filtering on boolean fields', function () {
    Organization::factory()->count(3)->create(['is_active' => true]);
    Organization::factory()->count(2)->create(['is_active' => false]);

    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?filter[is_active]=true');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');

    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?filter[is_active]=false');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('list endpoint supports exact filtering on UUID fields', function () {
    $org1 = Organization::factory()->create(['name' => 'Org 1']);
    Organization::factory()->create(['name' => 'Org 2']);

    $response = actingAs($this->user)
        ->getJson("/api/v1/organization?filter[id]={$org1->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $org1->id);
});

test('list endpoint supports sorting ascending', function () {
    Organization::factory()->create(['name' => 'Zebra Corp']);
    Organization::factory()->create(['name' => 'Alpha Inc']);
    Organization::factory()->create(['name' => 'Beta LLC']);

    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?sort=name');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.attributes.name', 'Alpha Inc')
        ->assertJsonPath('data.1.attributes.name', 'Beta LLC')
        ->assertJsonPath('data.2.attributes.name', 'Zebra Corp');
});

test('list endpoint supports sorting descending', function () {
    Organization::factory()->create(['name' => 'Zebra Corp']);
    Organization::factory()->create(['name' => 'Alpha Inc']);
    Organization::factory()->create(['name' => 'Beta LLC']);

    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?sort=-name');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.attributes.name', 'Zebra Corp')
        ->assertJsonPath('data.1.attributes.name', 'Beta LLC')
        ->assertJsonPath('data.2.attributes.name', 'Alpha Inc');
});

test('list endpoint supports ILIKE search across multiple fields', function () {
    Organization::factory()->create(['name' => 'Acme Corporation', 'slug' => 'acme-corp']);
    Organization::factory()->create(['name' => 'Test Company', 'slug' => 'test-company']);
    Organization::factory()->create(['name' => 'Another Corp', 'vat_number' => 'BE0123456789']);

    // Search by name
    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?search=Acme');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    // Search by slug
    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?search=test-company');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    // Search by VAT number
    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?search=BE0123');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('list endpoint combines filters and sorting', function () {
    Organization::factory()->create(['name' => 'Active Zebra', 'is_active' => true]);
    Organization::factory()->create(['name' => 'Active Alpha', 'is_active' => true]);
    Organization::factory()->create(['name' => 'Inactive Beta', 'is_active' => false]);

    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?filter[is_active]=true&sort=name');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.attributes.name', 'Active Alpha')
        ->assertJsonPath('data.1.attributes.name', 'Active Zebra');
});

test('list endpoint combines search with filters', function () {
    Organization::factory()->create(['name' => 'Acme Active Corp', 'is_active' => true]);
    Organization::factory()->create(['name' => 'Acme Inactive Corp', 'is_active' => false]);
    Organization::factory()->create(['name' => 'Test Active Corp', 'is_active' => true]);

    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?search=Acme&filter[is_active]=true');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.name', 'Acme Active Corp');
});

test('list endpoint respects pagination per_page parameter', function () {
    Organization::factory()->count(50)->create();

    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?page[per_page]=10');

    $response->assertStatus(200)
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.page.per_page', 10);
});

test('list endpoint rejects non-allowed filters', function () {
    Organization::factory()->count(3)->create();

    // 'contact_details' is not in the allowed filters
    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?filter[contact_details]=test');

    // Spatie Query Builder throws an InvalidFilterQuery exception
    // which should be caught and returned as a JSON:API error
    $response->assertStatus(400);
});

test('list endpoint rejects non-allowed sorts', function () {
    Organization::factory()->count(3)->create();

    // 'vat_number' is not in the allowed sorts
    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?sort=vat_number');

    // Spatie Query Builder throws an InvalidSortQuery exception
    $response->assertStatus(400);
});

test('list endpoint returns empty array when no results match filters', function () {
    Organization::factory()->count(5)->create(['is_active' => true]);

    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?filter[is_active]=false');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

test('list endpoint requires authorization', function () {
    // Create a user without permissions
    $unauthorizedUser = User::factory()->create();

    Organization::factory()->count(3)->create();

    $response = actingAs($unauthorizedUser)
        ->getJson('/api/v1/organization');

    $response->assertStatus(403)
        ->assertJsonPath('errors.0.status', '403')
        ->assertJsonPath('errors.0.code', 'ACCESS_DENIED');
});

test('list endpoint supports date range filtering', function () {
    // Create organizations with different creation dates
    $old = Organization::factory()->create(['created_at' => now()->subDays(10)]);
    $recent = Organization::factory()->create(['created_at' => now()->subDays(2)]);
    $newest = Organization::factory()->create(['created_at' => now()]);

    // Filter by exact date
    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?filter[created_at]='.$recent->created_at->format('Y-m-d'));

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    // Filter by date range (from)
    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?filter[created_at][from]='.now()->subDays(3)->format('Y-m-d'));

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data'); // recent and newest

    // Filter by date range (to)
    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?filter[created_at][to]='.now()->subDays(5)->format('Y-m-d'));

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data'); // only old
});

test('list endpoint cursor pagination works correctly', function () {
    // Create 30 organizations
    Organization::factory()->count(30)->create();

    // Get first page
    $response = actingAs($this->user)
        ->getJson('/api/v1/organization?page[per_page]=10');

    $response->assertStatus(200)
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.page.has_more', true);

    // Get the cursor for next page
    $nextUrl = $response->json('links.next');
    expect($nextUrl)->not->toBeNull();

    // Extract cursor from URL
    parse_str(parse_url($nextUrl, PHP_URL_QUERY), $params);
    $cursor = $params['page']['cursor'] ?? null;
    expect($cursor)->not->toBeNull();

    // Get second page using cursor
    $response = actingAs($this->user)
        ->getJson("/api/v1/organization?page[per_page]=10&page[cursor]={$cursor}");

    $response->assertStatus(200)
        ->assertJsonCount(10, 'data');
});

test('list endpoint returns correct JSON:API structure', function () {
    $org = Organization::factory()->create([
        'name' => 'Test Organization',
        'slug' => 'test-org',
        'is_active' => true,
    ]);

    $response = actingAs($this->user)
        ->getJson('/api/v1/organization');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'type',
                    'id',
                    'attributes' => [
                        'name',
                        'slug',
                        'is_active',
                    ],
                ],
            ],
        ])
        ->assertJsonPath('data.0.type', 'organization')
        ->assertJsonPath('data.0.id', $org->id)
        ->assertJsonPath('data.0.attributes.name', 'Test Organization');
});
