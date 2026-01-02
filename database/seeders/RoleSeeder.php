<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Seeders;

use Blafast\Foundation\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear permission cache before seeding
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create global role: Superadmin (no organization_id)
        Role::firstOrCreate(
            [
                'name' => 'Superadmin',
                'guard_name' => 'api',
            ],
            [
                'organization_id' => null, // Global role
            ]
        );

        // Create organization-level role templates
        // These are template roles that will be cloned for each organization
        // They have organization_id = null to indicate they are templates
        $organizationRoles = [
            [
                'name' => 'Admin',
                'description' => 'Full control over an organization',
            ],
            [
                'name' => 'User',
                'description' => 'Standard user with module-specific permissions',
            ],
            [
                'name' => 'Viewer',
                'description' => 'Read-only access to organization data',
            ],
            [
                'name' => 'Consumer',
                'description' => 'Minimal role for external users (portal access)',
            ],
        ];

        foreach ($organizationRoles as $roleData) {
            Role::firstOrCreate(
                [
                    'name' => $roleData['name'],
                    'guard_name' => 'api',
                    'organization_id' => null, // Template - no specific organization
                ]
            );
        }
    }
}
