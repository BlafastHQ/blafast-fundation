<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Blafast\Foundation\Services\ModuleRegistry;
use Illuminate\Console\Command;

/**
 * List all discovered BlaFast modules.
 *
 * Displays a table of all BlaFast modules with their status and metadata.
 */
class ModulesListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blafast:modules:list
                            {--enabled : Only show enabled modules}
                            {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all discovered BlaFast modules';

    /**
     * Execute the console command.
     */
    public function handle(ModuleRegistry $registry): int
    {
        $modules = $this->option('enabled')
            ? $registry->enabled()
            : $registry->all();

        if ($modules->isEmpty()) {
            $this->warn('No BlaFast modules found.');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($modules->map(fn ($module) => $module->toArray())->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Version', 'Status', 'Providers', 'Description'],
            $modules->map(function ($module) {
                return [
                    $module->name,
                    $module->version,
                    $module->enabled ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>',
                    count($module->providers),
                    str($module->description)->limit(50),
                ];
            })
        );

        $enabledCount = $modules->where('enabled', true)->count();
        $totalCount = $modules->count();

        $this->newLine();
        $this->info("Total: {$totalCount} module(s) ({$enabledCount} enabled)");

        return self::SUCCESS;
    }
}
