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
     * @return array<int, Permission>
     */
    protected function registerExecPermissions(string $modelClass, string $slug, ?string $organizationId = null): array
    {
        $execPermissions = [];

        // Model-level exec permission (grants all methods)
        $execPermissions[] = Permission::firstOrCreate(
            [
                'name' => "exec.{$slug}",
                'guard_name' => 'api',
                'organization_id' => $organizationId,
            ]
        );

        /**
         * @var array<string, array<string, mixed>> $apiMethods
         *
         * @phpstan-ignore-next-line Method exists on models with ExposesApiMethods trait
         */
        $apiMethods = $modelClass::apiMethods();

        // Method-level exec permissions
        foreach ($apiMethods as $methodSlug => $config) {
            $permissionName = "exec.{$slug}.{$methodSlug}";

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
     */
    public function assignDefaultRights(string $modelClass, ?string $organizationId = null): void
    {
        if (! method_exists($modelClass, 'defaultRights')) {
            return;
        }

        /** @var array<string, mixed> $rights */
        $rights = $modelClass::defaultRights();
        $slug = $this->getModelSlug($modelClass);

        // Apply CRUD rights
        $this->applyCrudRights($slug, $rights, $organizationId);

        // Apply exec rights if model has apiMethods
        if (isset($rights['exec']) && method_exists($modelClass, 'apiMethods')) {
            $this->applyExecRights($slug, $rights['exec'], $modelClass, $organizationId);
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Apply CRUD permissions to roles.
     *
     * @param  array<string, mixed>  $rights
     */
    protected function applyCrudRights(string $slug, array $rights, ?string $organizationId): void
    {
        $actions = ['view', 'create', 'update', 'delete', 'list'];

        foreach ($actions as $action) {
            if (! isset($rights[$action])) {
                continue;
            }

            $permission = "{$action}_{$slug}";

            foreach ($rights[$action] as $roleName) {
                $role = Role::where('name', $roleName)
                    ->where('guard_name', 'api')
                    ->where('organization_id', $organizationId)
                    ->first();

                if ($role && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }

    /**
     * Apply exec permissions to roles.
     *
     * @param  array<string, array<string>|string>  $execRights
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    protected function applyExecRights(string $slug, array $execRights, string $modelClass, ?string $organizationId): void
    {
        /**
         * @var array<string, array<string, mixed>> $apiMethods
         *
         * @phpstan-ignore-next-line Method exists on models with ExposesApiMethods trait
         */
        $apiMethods = $modelClass::apiMethods();
        $allMethods = array_keys($apiMethods);

        foreach ($execRights as $roleName => $methods) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'api')
                ->where('organization_id', $organizationId)
                ->first();

            if (! $role) {
                continue;
            }

            // Handle wildcard - give model-level permission
            if ($methods === ['*'] || $methods === '*') {
                $permission = "exec.{$slug}";
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }

                continue;
            }

            // Give specific method permissions
            foreach ($methods as $methodSlug) {
                if (! in_array($methodSlug, $allMethods)) {
                    continue;
                }

                $permission = "exec.{$slug}.{$methodSlug}";
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
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

    /**
     * Register permissions from all BlaFast modules.
     */
    public function registerModulePermissions(ModuleRegistry $registry): void
    {
        $modules = $registry->enabled();

        foreach ($modules as $module) {
            $this->registerPermissionsFromModule($module->name);
        }
    }

    /**
     * Register permissions from a specific module.
     */
    public function registerPermissionsFromModule(string $moduleName): void
    {
        $configKey = str_replace('/', '-', $moduleName);
        $permissions = config("{$configKey}.permissions", []);

        if (empty($permissions)) {
            return;
        }

        // Permissions are stored in config but actual registration
        // happens via the PermissionsSyncCommand which creates
        // database records. This method is for future extensibility.
    }

    /**
     * Clear cached permissions for a specific module.
     */
    public function clearModuleCache(string $moduleName): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Get all permissions from modules.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    public function getModulePermissions(ModuleRegistry $registry): array
    {
        $modules = $registry->enabled();
        $result = [];

        foreach ($modules as $module) {
            $configKey = str_replace('/', '-', $module->name);
            $permissions = config("{$configKey}.permissions", []);

            if (! empty($permissions)) {
                $result[$module->name] = $permissions;
            }
        }

        return $result;
    }

    /**
     * Sync all permissions for all registered models.
     */
    public function syncAll(?string $organizationId = null): void
    {
        $registry = app(\Blafast\Foundation\Services\ModelRegistry::class);

        foreach ($registry->all() as $modelClass) {
            $this->registerPermissionsForModel($modelClass, $organizationId);
            $this->assignDefaultRights($modelClass, $organizationId);
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
