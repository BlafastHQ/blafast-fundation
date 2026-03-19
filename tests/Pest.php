<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

require_once __DIR__.'/Helpers.php';

uses(TestCase::class, RefreshDatabase::class)
    ->in('Feature', 'Unit', 'Traits', 'Models');

// Global test helpers for authentication and organization context

/**
 * Act as a regular user.
 */
function actingAsUser(array $attributes = []): TestCase
{
    $userClass = config('auth.providers.users.model', 'App\\Models\\User');
    $user = $userClass::factory()->create($attributes);

    return test()->actingAs($user, 'sanctum');
}

/**
 * Act as a Superadmin user.
 */
function actingAsSuperadmin(): TestCase
{
    $userClass = config('auth.providers.users.model', 'App\\Models\\User');
    $user = $userClass::factory()->create();

    // Assign Superadmin role
    $role = Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'api',
    ]);
    $user->assignRole($role);

    return test()->actingAs($user, 'sanctum');
}

/**
 * Act as an organization admin.
 */
function actingAsOrgAdmin(?Organization $org = null): TestCase
{
    $org ??= Organization::factory()->create();

    $userClass = config('auth.providers.users.model', 'App\\Models\\User');
    $user = $userClass::factory()->create();

    // Attach user to organization with Admin role
    $user->organizations()->attach($org->id, [
        'id' => (string) Str::uuid(),
        'role' => 'Admin',
        'is_active' => true,
        'joined_at' => now(),
    ]);

    return test()
        ->actingAs($user, 'sanctum')
        ->withHeader('X-Organization-Id', $org->id);
}

/**
 * Add organization header to request.
 */
function withOrganization(?Organization $org = null): TestCase
{
    $org ??= Organization::factory()->create();

    return test()->withHeader('X-Organization-Id', $org->id);
}
