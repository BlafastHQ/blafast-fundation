<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Seeders;

use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear permission cache before seeding
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Foundation module permissions
        $permissions = [
            // Organization management
            'view_organizations',
            'create_organizations',
            'update_organizations',
            'delete_organizations',
            'list_organizations',

            // User management
            'view_users',
            'create_users',
            'update_users',
            'delete_users',
            'list_users',

            // Role management
            'view_roles',
            'create_roles',
            'update_roles',
            'delete_roles',
            'list_roles',

            // Permission management
            'view_permissions',
            'list_permissions',

            // Activity log
            'view_activity_log',
            'list_activity_log',

            // Settings
            'view_settings',
            'update_settings',
        ];

        // Create global permissions (organization_id = null)
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                [
                    'name' => $permission,
                    'guard_name' => 'api',
                    'organization_id' => null, // Global permission
                ]
            );
        }

        // Assign permissions to Superadmin role
        $this->assignPermissionsToSuperadmin($permissions);

        // Assign default permissions to organization roles
        $this->assignPermissionsToOrganizationRoles();
    }

    /**
     * Assign all permissions to Superadmin role.
     *
     * @param  array<int, string>  $permissions
     */
    private function assignPermissionsToSuperadmin(array $permissions): void
    {
        $superadmin = Role::where('name', 'Superadmin')
            ->where('guard_name', 'api')
            ->whereNull('organization_id')
            ->first();

        if ($superadmin) {
            $superadmin->givePermissionTo($permissions);
        }
    }

    /**
     * Assign default permissions to organization-level roles.
     */
    private function assignPermissionsToOrganizationRoles(): void
    {
        // Admin role gets all organization-scoped permissions
        $admin = Role::where('name', 'Admin')
            ->where('guard_name', 'api')
            ->whereNull('organization_id')
            ->first();

        if ($admin) {
            $admin->givePermissionTo([
                'view_organizations',
                'update_organizations',
                'view_users',
                'create_users',
                'update_users',
                'delete_users',
                'list_users',
                'view_roles',
                'list_roles',
                'view_permissions',
                'list_permissions',
                'view_activity_log',
                'list_activity_log',
                'view_settings',
                'update_settings',
            ]);
        }

        // User role gets basic permissions
        $user = Role::where('name', 'User')
            ->where('guard_name', 'api')
            ->whereNull('organization_id')
            ->first();

        if ($user) {
            $user->givePermissionTo([
                'view_users',
                'list_users',
                'view_settings',
            ]);
        }

        // Viewer role gets read-only permissions
        $viewer = Role::where('name', 'Viewer')
            ->where('guard_name', 'api')
            ->whereNull('organization_id')
            ->first();

        if ($viewer) {
            $viewer->givePermissionTo([
                'view_users',
                'list_users',
                'view_activity_log',
                'list_activity_log',
            ]);
        }

        // Consumer role gets minimal permissions
        $consumer = Role::where('name', 'Consumer')
            ->where('guard_name', 'api')
            ->whereNull('organization_id')
            ->first();

        if ($consumer) {
            $consumer->givePermissionTo([
                'view_users',
            ]);
        }
    }
}
