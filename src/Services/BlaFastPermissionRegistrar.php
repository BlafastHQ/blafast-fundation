<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Illuminate\Support\Str;

class BlaFastPermissionRegistrar
{
    /**
     * Register CRUD permissions for a model.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  string|null  $organizationId
     * @return array<int, Permission>
     */
    public function registerPermissionsForModel(string $modelClass, ?string $organizationId = null): array
    {
        $slug = $this->getModelSlug($modelClass);

        $permissions = [
            "view_{$slug}",
            "create_{$slug}",
            "update_{$slug}",
            "delete_{$slug}",
            "list_{$slug}",
        ];

        $createdPermissions = [];

        foreach ($permissions as $permissionName) {
            $createdPermissions[] = Permission::firstOrCreate(
                [
                    'name' => $permissionName,
                    'guard_name' => 'api',
                    'organization_id' => $organizationId,
                ]
            );
        }

        // Register exec permissions if model uses ExposesApiMethods
        if (method_exists($modelClass, 'apiMethods')) {
            $execPermissions = $this->registerExecPermissions($modelClass, $slug, $organizationId);
            $createdPermissions = array_merge($createdPermissions, $execPermissions);
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $createdPermissions;
    }

    /**
     * Register exec permissions for models with exposed API methods.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  string  $slug
     * @param  string|null  $organizationId
     * @return array<int, Permission>
     */
    protected function registerExecPermissions(string $modelClass, string $slug, ?string $organizationId = null): array
    {
        $execPermissions = [];

        /**
         * @var array<string, array<string, mixed>> $apiMethods
         * @phpstan-ignore-next-line Method exists on models with ExposesApiMethods trait
         */
        $apiMethods = $modelClass::apiMethods();

        foreach ($apiMethods as $methodName => $config) {
            $permissionName = "exec_{$slug}_{$methodName}";

            $execPermissions[] = Permission::firstOrCreate(
                [
                    'name' => $permissionName,
                    'guard_name' => 'api',
                    'organization_id' => $organizationId,
                ]
            );
        }

        return $execPermissions;
    }

    /**
     * Assign default permissions to a role based on model's defaultRights configuration.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  string|null  $organizationId
     */
    public function assignDefaultRights(string $modelClass, ?string $organizationId = null): void
    {
        if (! method_exists($modelClass, 'defaultRights')) {
            return;
        }

        /** @var array<string, array<string>> $rights */
        $rights = $modelClass::defaultRights();

        foreach ($rights as $roleName => $permissions) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'api')
                ->where('organization_id', $organizationId)
                ->first();

            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Get the slug for a model class.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    protected function getModelSlug(string $modelClass): string
    {
        $className = class_basename($modelClass);

        return Str::snake($className);
    }

    /**
     * Register module permissions.
     *
     * @param  string  $moduleName
     * @param  string|null  $organizationId
     * @return Permission
     */
    public function registerModulePermission(string $moduleName, ?string $organizationId = null): Permission
    {
        $permissionName = "view_{$moduleName}_module";

        $permission = Permission::firstOrCreate(
            [
                'name' => $permissionName,
                'guard_name' => 'api',
                'organization_id' => $organizationId,
            ]
        );

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $permission;
    }

    /**
     * Copy template roles to an organization.
     *
     * @param  string  $organizationId
     * @return array<int, Role>
     */
    public function copyTemplateRolesToOrganization(string $organizationId): array
    {
        $templateRoles = Role::whereNull('organization_id')
            ->where('name', '!=', 'Superadmin')
            ->with('permissions')
            ->get();

        $copiedRoles = [];

        foreach ($templateRoles as $templateRole) {
            /** @var Role $templateRole */
            $newRole = Role::firstOrCreate(
                [
                    'name' => $templateRole->name,
                    'guard_name' => $templateRole->guard_name,
                    'organization_id' => $organizationId,
                ]
            );

            // Copy permissions from template
            $permissionIds = $templateRole->permissions->pluck('id')->toArray();
            if (! empty($permissionIds)) {
                $newRole->syncPermissions($permissionIds);
            }

            $copiedRoles[] = $newRole;
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $copiedRoles;
    }
}
