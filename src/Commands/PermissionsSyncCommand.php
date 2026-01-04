<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Blafast\Foundation\Services\ModuleRegistry;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sync permissions from all BlaFast modules.
 *
 * This command discovers permissions defined in module configuration
 * and synchronizes them with the database.
 */
class PermissionsSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blafast:permissions:sync
                            {--module= : Only sync permissions for a specific module}
                            {--force : Force sync without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync permissions from BlaFast modules';

    /**
     * Execute the console command.
     */
    public function handle(ModuleRegistry $registry, PermissionRegistrar $registrar): int
    {
        if (! class_exists(Permission::class)) {
            $this->error('Spatie Permission package is not installed.');

            return self::FAILURE;
        }

        $modules = $this->option('module')
            ? collect([$registry->get($this->option('module'))])->filter()
            : $registry->enabled();

        if ($modules->isEmpty()) {
            $this->warn('No modules found to sync.');

            return self::SUCCESS;
        }

        $this->info('Scanning modules for permissions...');

        $permissions = collect();

        foreach ($modules as $module) {
            $modulePermissions = $this->getModulePermissions($module->name);

            if (! empty($modulePermissions)) {
                $this->line("  • {$module->name}: ".count($modulePermissions).' permission(s)');
                $permissions = $permissions->merge($modulePermissions);
            }
        }

        if ($permissions->isEmpty()) {
            $this->info('No permissions found in modules.');

            return self::SUCCESS;
        }

        $this->newLine();

        if (! $this->option('force') && ! $this->confirm("Sync {$permissions->count()} permission(s)?", true)) {
            $this->info('Permission sync cancelled.');

            return self::SUCCESS;
        }

        $created = 0;
        $existing = 0;

        foreach ($permissions as $permission) {
            $permissionModel = Permission::firstOrCreate(
                ['name' => $permission['name']],
                [
                    'guard_name' => $permission['guard_name'] ?? 'web',
                    'description' => $permission['description'] ?? null,
                ]
            );

            if ($permissionModel->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        // Reset cached permissions
        $registrar->forgetCachedPermissions();

        $this->newLine();
        $this->info("Permission sync completed:");
        $this->line("  • Created: {$created}");
        $this->line("  • Existing: {$existing}");

        return self::SUCCESS;
    }

    /**
     * Get permissions defined in a module's configuration.
     *
     * @return array<int, array<string, string>>
     */
    protected function getModulePermissions(string $moduleName): array
    {
        $configKey = str_replace('/', '-', $moduleName);

        return config("{$configKey}.permissions", []);
    }
}
