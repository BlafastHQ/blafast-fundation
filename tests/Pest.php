<?php

declare(strict_types=1);

use Blafast\Foundation\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/Helpers.php';

uses(TestCase::class, RefreshDatabase::class)
    ->in('Feature', 'Unit');

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
    $role = \Spatie\Permission\Models\Role::firstOrCreate([
        'name' => 'Superadmin',
        'guard_name' => 'api',
    ]);
    $user->assignRole($role);

    return test()->actingAs($user, 'sanctum');
}

/**
 * Act as an organization admin.
 */
function actingAsOrgAdmin(\Blafast\Foundation\Models\Organization $org = null): TestCase
{
    $org ??= \Blafast\Foundation\Models\Organization::factory()->create();

    $userClass = config('auth.providers.users.model', 'App\\Models\\User');
    $user = $userClass::factory()->create();

    // Attach user to organization with Admin role
    $user->organizations()->attach($org->id, [
        'id' => (string) \Illuminate\Support\Str::uuid(),
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
function withOrganization(\Blafast\Foundation\Models\Organization $org = null): TestCase
{
    $org ??= \Blafast\Foundation\Models\Organization::factory()->create();

    return test()->withHeader('X-Organization-Id', $org->id);
}
