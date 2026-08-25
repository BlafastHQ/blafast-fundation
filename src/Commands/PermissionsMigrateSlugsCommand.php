<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Blafast\Foundation\Services\ModelRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Renames permissions created under the legacy Str::snake() slug scheme to the
 * canonical getApiSlug() (kebab-case) names — task 7 / audit H7. Before the
 * unification, permissions:sync created `exec.sales_order.*` while the runtime
 * checked `exec.sales-order.*`, leaving granted rows permanently unreachable.
 * Also folds the legacy plural organization CRUD names into the canonical
 * singular ones.
 */
class PermissionsMigrateSlugsCommand extends Command
{
    protected $signature = 'blafast:permissions:migrate-slugs {--dry-run : List the renames without applying them}';

    protected $description = 'Rename permissions from legacy snake/plural slugs to the canonical kebab slugs';

    public function handle(ModelRegistry $registry): int
    {
        $renames = [];

        foreach (array_values($registry->all()) as $modelClass) {
            $legacy = Str::snake(class_basename($modelClass));
            $canonical = method_exists($modelClass, 'getApiSlug')
                ? $modelClass::getApiSlug()
                : Str::kebab(class_basename($modelClass));

            if ($legacy === $canonical) {
                continue;
            }

            foreach (['view', 'create', 'update', 'delete', 'list'] as $action) {
                $renames["{$action}_{$legacy}"] = "{$action}_{$canonical}";
            }

            $renames["exec.{$legacy}"] = "exec.{$canonical}";
            $renames["exec.{$legacy}."] = "exec.{$canonical}."; // prefix rename, handled below
        }

        // The seeded plural organization names predate the canonical singular ones.
        foreach (['view', 'create', 'update', 'delete', 'list'] as $action) {
            $renames["{$action}_organizations"] = "{$action}_organization";
        }

        $permissionModel = config('permission.models.permission');
        $dryRun = (bool) $this->option('dry-run');
        $applied = 0;

        foreach ($permissionModel::query()->orderBy('name')->get() as $permission) {
            $target = null;

            if (isset($renames[$permission->name])) {
                $target = $renames[$permission->name];
            } else {
                foreach ($renames as $from => $to) {
                    if (str_ends_with($from, '.') && str_starts_with($permission->name, $from)) {
                        $target = $to.substr($permission->name, strlen($from));
                        break;
                    }
                }
            }

            if ($target === null || $target === $permission->name) {
                continue;
            }

            $exists = $permissionModel::query()
                ->where('name', $target)
                ->where('guard_name', $permission->guard_name)
                ->when(
                    config('permission.teams'),
                    fn ($q) => $q->where(config('permission.column_names.team_foreign_key'), $permission->{config('permission.column_names.team_foreign_key')})
                )
                ->exists();

            if ($exists) {
                $this->warn("skip {$permission->name} → {$target} (target already exists; merge grants manually)");

                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '')."{$permission->name} → {$target}");

            if (! $dryRun) {
                $permission->update(['name' => $target]);
                $applied++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info($dryRun ? 'Dry run complete.' : "Renamed {$applied} permission(s).");

        return self::SUCCESS;
    }
}
