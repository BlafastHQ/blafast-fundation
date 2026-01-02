<?php

declare(strict_types=1);

use Blafast\Foundation\Database\Seeders\RoleSeeder;
use Blafast\Foundation\Http\Middleware\EnsureOrganizationContext;
use Blafast\Foundation\Http\Middleware\ResolveOrganizationContext;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles
    (new RoleSeeder())->run();

    // Create a test route that uses the middleware
    Route::middleware(['web', ResolveOrganizationContext::class])
        ->get('/test-org-context', function () {
            $context = app(OrganizationContext::class);

            return response()->json([
                'has_context' => $context->hasContext(),
                'is_global' => $context->isGlobalContext(),
                'organization_id' => $context->id(),
                'organization_slug' => $context->slug(),
            ]);
        });

    Route::middleware(['web', ResolveOrganizationContext::class, EnsureOrganizationContext::class])
        ->get('/test-org-required', function () {
            return response()->json(['success' => true]);
        });
});

test('request with valid organization header sets context correctly', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $organization = Organization::create([
        'name' => 'Test Organization',
        'slug' => 'test-org',
    ]);

    $organization->addUser($user, 'member');

    $response = $this->actingAs($user)
        ->withHeader('X-Organization-Id', $organization->id)
        ->get('/test-org-context');

    $response->assertStatus(200);
    $response->assertJson([
        'has_context' => true,
        'is_global' => false,
        'organization_id' => $organization->id,
        'organization_slug' => $organization->slug,
    ]);
});

test('request without header for regular user returns 400', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->actingAs($user)
        ->get('/test-org-context');

    $response->assertStatus(400);
    $response->assertJson([
        'errors' => [[
            'status' => '400',
            'code' => 'MISSING_ORGANIZATION',
            'title' => 'Organization Required',
        ]],
    ]);
});

test('request without header for superadmin sets global context', function () {
    $superadmin = User::create([
        'name' => 'Superadmin',
        'email' => 'superadmin@example.com',
        'password' => bcrypt('password'),
    ]);

    $superadminRole = Role::where('name', 'Superadmin')->first();
    $superadmin->assignRole($superadminRole);

    $response = $this->actingAs($superadmin)
        ->get('/test-org-context');

    $response->assertStatus(200);
    $response->assertJson([
        'has_context' => false,
        'is_global' => true,
        'organization_id' => null,
        'organization_slug' => null,
    ]);
});

test('request with invalid organization id returns 403', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->actingAs($user)
        ->withHeader('X-Organization-Id', '00000000-0000-0000-0000-000000000000')
        ->get('/test-org-context');

    $response->assertStatus(403);
    $response->assertJson([
        'errors' => [[
            'status' => '403',
            'code' => 'ORGANIZATION_ACCESS_DENIED',
            'title' => 'Access Denied',
        ]],
    ]);
});

test('request for organization user does not belong to returns 403', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $organization = Organization::create([
        'name' => 'Other Organization',
        'slug' => 'other-org',
    ]);

    // User is not added to the organization

    $response = $this->actingAs($user)
        ->withHeader('X-Organization-Id', $organization->id)
        ->get('/test-org-context');

    $response->assertStatus(403);
    $response->assertJson([
        'errors' => [[
            'status' => '403',
            'code' => 'ORGANIZATION_ACCESS_DENIED',
            'title' => 'Access Denied',
        ]],
    ]);
});

test('request for inactive membership returns 403', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $organization = Organization::create([
        'name' => 'Test Organization',
        'slug' => 'test-org',
    ]);

    $organization->addUser($user, 'member');
    $organization->removeUser($user); // This makes the membership inactive

    $response = $this->actingAs($user)
        ->withHeader('X-Organization-Id', $organization->id)
        ->get('/test-org-context');

    $response->assertStatus(403);
    $response->assertJson([
        'errors' => [[
            'status' => '403',
            'code' => 'MEMBERSHIP_INACTIVE',
            'title' => 'Membership Inactive',
        ]],
    ]);
});

test('session fallback works when header is omitted', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $organization = Organization::create([
        'name' => 'Test Organization',
        'slug' => 'test-org',
    ]);

    $organization->addUser($user, 'member');

    // First request with header - sets session
    $response = $this->actingAs($user)
        ->withHeader('X-Organization-Id', $organization->id)
        ->get('/test-org-context');

    $response->assertStatus(200);

    // Second request without header - should use session fallback
    $response = $this->actingAs($user)
        ->get('/test-org-context');

    $response->assertStatus(200);
    $response->assertJson([
        'has_context' => true,
        'is_global' => false,
        'organization_id' => $organization->id,
    ]);
});

test('unauthenticated request skips middleware', function () {
    $response = $this->get('/test-org-context');

    $response->assertStatus(200);
    $response->assertJson([
        'has_context' => false,
        'is_global' => false,
        'organization_id' => null,
    ]);
});

test('superadmin can access specific organization with header', function () {
    $superadmin = User::create([
        'name' => 'Superadmin',
        'email' => 'superadmin@example.com',
        'password' => bcrypt('password'),
    ]);

    $superadminRole = Role::where('name', 'Superadmin')->first();
    $superadmin->assignRole($superadminRole);

    $organization = Organization::create([
        'name' => 'Test Organization',
        'slug' => 'test-org',
    ]);

    $organization->addUser($superadmin, 'admin');

    $response = $this->actingAs($superadmin)
        ->withHeader('X-Organization-Id', $organization->id)
        ->get('/test-org-context');

    $response->assertStatus(200);
    $response->assertJson([
        'has_context' => true,
        'is_global' => false,
        'organization_id' => $organization->id,
    ]);
});

test('ensure organization context middleware blocks global context', function () {
    $superadmin = User::create([
        'name' => 'Superadmin',
        'email' => 'superadmin@example.com',
        'password' => bcrypt('password'),
    ]);

    $superadminRole = Role::where('name', 'Superadmin')->first();
    $superadmin->assignRole($superadminRole);

    $response = $this->actingAs($superadmin)
        ->get('/test-org-required');

    $response->assertStatus(400);
    $response->assertJson([
        'errors' => [[
            'status' => '400',
            'code' => 'ORGANIZATION_REQUIRED',
            'title' => 'Organization Context Required',
        ]],
    ]);
});

test('ensure organization context middleware allows valid organization context', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $organization = Organization::create([
        'name' => 'Test Organization',
        'slug' => 'test-org',
    ]);

    $organization->addUser($user, 'member');

    $response = $this->actingAs($user)
        ->withHeader('X-Organization-Id', $organization->id)
        ->get('/test-org-required');

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
});

test('organization context is properly populated with user', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $organization = Organization::create([
        'name' => 'Test Organization',
        'slug' => 'test-org',
    ]);

    $organization->addUser($user, 'member');

    $this->actingAs($user)
        ->withHeader('X-Organization-Id', $organization->id)
        ->get('/test-org-context');

    $context = app(OrganizationContext::class);

    expect($context->hasContext())->toBeTrue()
        ->and($context->organization())->not->toBeNull()
        ->and($context->organization()->id)->toBe($organization->id)
        ->and($context->user())->not->toBeNull()
        ->and($context->user()->id)->toBe($user->id);
});
