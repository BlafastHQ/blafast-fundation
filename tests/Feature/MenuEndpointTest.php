<?php

declare(strict_types=1);

use Blafast\Foundation\Services\MenuRegistry;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup permissions
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
});

test('user-menu endpoint requires authentication', function () {
    $response = $this->getJson('/api/v1/user-menu');

    $response->assertStatus(401);
});

test('user-menu endpoint returns empty menu for unauthenticated user', function () {
    // Make request without auth header
    $response = $this->json('GET', '/api/v1/user-menu');

    $response->assertStatus(401);
});

test('user-menu endpoint returns JSON:API formatted response', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'included',
        ]);
});

test('user-menu returns items without permission requirement', function () {
    $user = User::factory()->create();

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->add([
        'label' => 'dashboard',
        'route' => 'dashboard',
        'icon' => 'icon-dashboard',
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'menu-item')
        ->assertJsonPath('data.0.attributes.label', 'dashboard')
        ->assertJsonPath('data.0.attributes.icon', 'icon-dashboard');
});

test('user-menu filters items based on permissions', function () {
    $user = User::factory()->create();

    // Create permissions
    Permission::create(['name' => 'view_admin', 'guard_name' => 'web']);
    Permission::create(['name' => 'view_secret', 'guard_name' => 'web']);

    // Give user only view_admin permission
    $user->givePermissionTo('view_admin');

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->addMany([
        [
            'label' => 'admin',
            'route' => 'admin',
            'permission' => 'view_admin',
        ],
        [
            'label' => 'secret',
            'route' => 'secret',
            'permission' => 'view_secret',
        ],
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.label', 'admin');
});

test('user-menu filters children based on permissions', function () {
    $user = User::factory()->create();

    Permission::create(['name' => 'view_billing', 'guard_name' => 'web']);
    Permission::create(['name' => 'view_invoices', 'guard_name' => 'web']);
    Permission::create(['name' => 'view_payments', 'guard_name' => 'web']);

    // Give user billing and invoices but not payments
    $user->givePermissionTo(['view_billing', 'view_invoices']);

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->add([
        'label' => 'billing',
        'tag' => 'billing',
        'permission' => 'view_billing',
        'children' => [
            ['label' => 'invoices', 'route' => 'invoices', 'permission' => 'view_invoices'],
            ['label' => 'payments', 'route' => 'payments', 'permission' => 'view_payments'],
        ],
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.label', 'billing')
        ->assertJsonCount(1, 'data.0.relationships.children.data')
        ->assertJsonCount(1, 'included')
        ->assertJsonPath('included.0.attributes.label', 'invoices');
});

test('user-menu excludes parent without accessible children or route', function () {
    $user = User::factory()->create();

    Permission::create(['name' => 'view_admin', 'guard_name' => 'web']);
    Permission::create(['name' => 'view_secret_section', 'guard_name' => 'web']);

    $user->givePermissionTo('view_admin');

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->add([
        'label' => 'admin',
        'permission' => 'view_admin',
        // No route/url, only children
        'children' => [
            ['label' => 'secret', 'route' => 'secret', 'permission' => 'view_secret_section'],
        ],
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

test('user-menu includes parent with route even without accessible children', function () {
    $user = User::factory()->create();

    Permission::create(['name' => 'view_settings', 'guard_name' => 'web']);
    Permission::create(['name' => 'view_advanced', 'guard_name' => 'web']);

    $user->givePermissionTo('view_settings');

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->add([
        'label' => 'settings',
        'route' => 'settings',
        'permission' => 'view_settings',
        'children' => [
            ['label' => 'advanced', 'route' => 'advanced', 'permission' => 'view_advanced'],
        ],
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.label', 'settings')
        ->assertJsonMissing(['relationships']);
});

test('user-menu respects hierarchical permission structure', function () {
    $user = User::factory()->create();

    Permission::create(['name' => 'view_billing', 'guard_name' => 'web']);
    Permission::create(['name' => 'view_invoices', 'guard_name' => 'web']);
    Permission::create(['name' => 'create_invoice', 'guard_name' => 'web']);

    $user->givePermissionTo(['view_billing', 'view_invoices', 'create_invoice']);

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->add([
        'label' => 'billing',
        'tag' => 'billing',
        'permission' => 'view_billing',
        'children' => [
            [
                'label' => 'invoices',
                'tag' => 'billing.invoices',
                'permission' => 'view_invoices',
                'children' => [
                    ['label' => 'create', 'route' => 'invoices.create', 'permission' => 'create_invoice'],
                    ['label' => 'list', 'route' => 'invoices.index', 'permission' => 'view_invoices'],
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.label', 'billing');

    // Check that invoices item is in included
    $invoicesItem = collect($response->json('included'))
        ->firstWhere('attributes.label', 'invoices');

    expect($invoicesItem)->not->toBeNull()
        ->and($invoicesItem['relationships']['children']['data'])->toHaveCount(2);
});

test('user-menu handles deeply nested structures', function () {
    $user = User::factory()->create();

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->add([
        'label' => 'level1',
        'children' => [
            [
                'label' => 'level2',
                'children' => [
                    [
                        'label' => 'level3',
                        'route' => 'deep',
                    ],
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    // Should have level2 and level3 in included
    expect($response->json('included'))->toHaveCount(2);
});

test('user-menu caches response', function () {
    $user = User::factory()->create();

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->add(['label' => 'dashboard', 'route' => 'dashboard']);

    // First request
    $response1 = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    // Add new item to registry
    $registry->add(['label' => 'new-item', 'route' => 'new']);

    // Second request should be cached and not include new item
    $response2 = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    expect($response1->json('data'))->toEqual($response2->json('data'));
});

test('user-menu response includes order field', function () {
    $user = User::factory()->create();

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->add(['label' => 'first', 'route' => 'first', 'order' => 100]);
    $registry->add(['label' => 'second', 'route' => 'second', 'order' => 200]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.attributes.order', 100)
        ->assertJsonPath('data.1.attributes.order', 200);
});

test('user-menu response includes icon field', function () {
    $user = User::factory()->create();

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->add(['label' => 'settings', 'route' => 'settings', 'icon' => 'icon-settings']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.attributes.icon', 'icon-settings');
});

test('user-menu uses tag as id when available', function () {
    $user = User::factory()->create();

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->add(['label' => 'billing', 'route' => 'billing', 'tag' => 'main.billing']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.id', 'main.billing');
});

test('user-menu uses slug as id when tag not available', function () {
    $user = User::factory()->create();

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->add(['label' => 'User Settings', 'route' => 'settings']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.id', 'user-settings');
});

test('user-menu works with role-based permissions', function () {
    $user = User::factory()->create();

    $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Permission::create(['name' => 'access_admin_panel', 'guard_name' => 'web']);
    $adminRole->givePermissionTo('access_admin_panel');

    $user->assignRole($adminRole);

    $registry = app(MenuRegistry::class);
    $registry->clear();
    $registry->add(['label' => 'admin', 'route' => 'admin', 'permission' => 'access_admin_panel']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user-menu');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.label', 'admin');
});
