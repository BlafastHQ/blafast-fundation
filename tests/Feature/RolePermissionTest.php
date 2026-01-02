<?php

declare(strict_types=1);

use Blafast\Foundation\Database\Seeders\PermissionSeeder;
use Blafast\Foundation\Database\Seeders\RoleSeeder;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Services\BlaFastPermissionRegistrar;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions directly
    (new RoleSeeder())->run();
    (new PermissionSeeder())->run();
});

test('superadmin role is created without organization', function () {
    $role = Role::where('name', 'Superadmin')
        ->where('guard_name', 'api')
        ->first();

    expect($role)->not->toBeNull()
        ->and($role->organization_id)->toBeNull()
        ->and($role->isSuperadmin())->toBeTrue()
        ->and($role->isGlobal())->toBeTrue();
});

test('organization roles are created as templates', function () {
    $roles = Role::whereNull('organization_id')
        ->where('name', '!=', 'Superadmin')
        ->pluck('name')
        ->toArray();

    expect($roles)->toContain('Admin', 'User', 'Viewer', 'Consumer');
});

test('permissions are created globally', function () {
    $permission = Permission::where('name', 'view_users')
        ->where('guard_name', 'api')
        ->first();

    expect($permission)->not->toBeNull()
        ->and($permission->organization_id)->toBeNull();
});

test('user can be assigned a role in organization context', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $organization = Organization::create([
        'name' => 'Test Organization',
        'slug' => 'test-org',
    ]);

    // Set permission team ID
    setPermissionsTeamId($organization->id);

    // Create role for this organization
    $role = Role::firstOrCreate([
        'name' => 'Admin',
        'guard_name' => 'api',
        'organization_id' => $organization->id,
    ]);

    $user->assignRole($role);

    expect($user->hasRole('Admin', 'api', $organization->id))->toBeTrue()
        ->and($user->hasOrganizationRole('Admin', $organization))->toBeTrue();
});

test('user can have different roles in different organizations', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $org1 = Organization::create([
        'name' => 'Organization 1',
        'slug' => 'org-1',
    ]);

    $org2 = Organization::create([
        'name' => 'Organization 2',
        'slug' => 'org-2',
    ]);

    // Assign Admin role in org1
    setPermissionsTeamId($org1->id);
    $role1 = Role::firstOrCreate([
        'name' => 'Admin',
        'guard_name' => 'api',
        'organization_id' => $org1->id,
    ]);
    $user->assignRole($role1);

    // Assign Viewer role in org2
    setPermissionsTeamId($org2->id);
    $role2 = Role::firstOrCreate([
        'name' => 'Viewer',
        'guard_name' => 'api',
        'organization_id' => $org2->id,
    ]);
    $user->assignRole($role2);

    // Check roles in org1
    setPermissionsTeamId($org1->id);
    $user->unsetRelation('roles')->unsetRelation('permissions');
    expect($user->hasRole('Admin', 'api', $org1->id))->toBeTrue()
        ->and($user->hasRole('Viewer', 'api', $org1->id))->toBeFalse();

    // Check roles in org2
    setPermissionsTeamId($org2->id);
    $user->unsetRelation('roles')->unsetRelation('permissions');
    expect($user->hasRole('Viewer', 'api', $org2->id))->toBeTrue()
        ->and($user->hasRole('Admin', 'api', $org2->id))->toBeFalse();
});

test('user can be assigned permissions in organization context', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $organization = Organization::create([
        'name' => 'Test Organization',
        'slug' => 'test-org',
    ]);

    setPermissionsTeamId($organization->id);

    $permission = Permission::where('name', 'view_users')
        ->where('guard_name', 'api')
        ->first();

    $user->givePermissionTo($permission);

    expect($user->hasPermissionTo('view_users', 'api', $organization->id))->toBeTrue()
        ->and($user->hasOrganizationPermission('view_users', $organization))->toBeTrue();
});

test('superadmin has all permissions', function () {
    $user = User::create([
        'name' => 'Superadmin User',
        'email' => 'superadmin@example.com',
        'password' => bcrypt('password'),
    ]);

    $superadminRole = Role::where('name', 'Superadmin')->first();
    $user->assignRole($superadminRole);

    expect($user->isSuperadmin())->toBeTrue()
        ->and($user->hasPermissionTo('view_users'))->toBeTrue()
        ->and($user->hasPermissionTo('create_users'))->toBeTrue()
        ->and($user->hasPermissionTo('delete_users'))->toBeTrue();
});

test('permission registrar can register CRUD permissions for model', function () {
    $registrar = new BlaFastPermissionRegistrar();

    $permissions = $registrar->registerPermissionsForModel(User::class);

    expect($permissions)->toHaveCount(5)
        ->and(Permission::where('name', 'view_user')->exists())->toBeTrue()
        ->and(Permission::where('name', 'create_user')->exists())->toBeTrue()
        ->and(Permission::where('name', 'update_user')->exists())->toBeTrue()
        ->and(Permission::where('name', 'delete_user')->exists())->toBeTrue()
        ->and(Permission::where('name', 'list_user')->exists())->toBeTrue();
});

test('permission registrar can copy template roles to organization', function () {
    $organization = Organization::create([
        'name' => 'New Organization',
        'slug' => 'new-org',
    ]);

    $registrar = new BlaFastPermissionRegistrar();
    $copiedRoles = $registrar->copyTemplateRolesToOrganization($organization->id);

    expect($copiedRoles)->toHaveCount(4) // Admin, User, Viewer, Consumer
        ->and(Role::where('organization_id', $organization->id)->count())->toBe(4);

    $adminRole = Role::where('name', 'Admin')
        ->where('organization_id', $organization->id)
        ->first();

    expect($adminRole)->not->toBeNull()
        ->and($adminRole->permissions->count())->toBeGreaterThan(0);
});

test('role scopes work correctly', function () {
    $organization = Organization::create([
        'name' => 'Test Organization',
        'slug' => 'test-org',
    ]);

    Role::create([
        'name' => 'OrgAdmin',
        'guard_name' => 'api',
        'organization_id' => $organization->id,
    ]);

    $globalRoles = Role::global()->pluck('name')->toArray();
    $orgRoles = Role::forOrganization($organization->id)->pluck('name')->toArray();

    expect($globalRoles)->toContain('Superadmin', 'Admin', 'User', 'Viewer', 'Consumer')
        ->and($orgRoles)->toContain('OrgAdmin')
        ->and($orgRoles)->not->toContain('Superadmin');
});

test('permission cache is cleared after role changes', function () {
    $role = Role::where('name', 'Admin')->whereNull('organization_id')->first();
    $permission = Permission::where('name', 'view_users')->first();

    // Give permission to role
    $role->givePermissionTo($permission);

    // Check that permission is in role
    $role->refresh();
    expect($role->hasPermissionTo('view_users'))->toBeTrue();

    // Revoke permission
    $role->revokePermissionTo($permission);

    // Check that permission is removed
    $role->refresh();
    expect($role->hasPermissionTo('view_users'))->toBeFalse();
});
