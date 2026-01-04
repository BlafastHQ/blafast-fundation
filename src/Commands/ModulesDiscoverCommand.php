<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Blafast\Foundation\Services\ModuleRegistry;
use Illuminate\Console\Command;

/**
 * Discover and cache BlaFast modules.
 *
 * This command scans installed Composer packages for BlaFast modules
 * and caches them for improved performance.
 */
class ModulesDiscoverCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blafast:modules:discover';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover and cache BlaFast modules from installed packages';

    /**
     * Execute the console command.
     */
    public function handle(ModuleRegistry $registry): int
    {
        $this->info('Discovering BlaFast modules...');

        $registry->rebuild();

        $modules = $registry->all();

        if ($modules->isEmpty()) {
            $this->warn('No BlaFast modules found.');

            return self::SUCCESS;
        }

        $this->info("Discovered {$modules->count()} module(s):");
        $this->newLine();

        foreach ($modules as $module) {
            $status = $module->enabled ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>';
            $this->line("  • {$module->name} ({$module->version}) - {$status}");

            if ($module->description) {
                $this->line("    {$module->description}");
            }

            if (! empty($module->providers)) {
                $this->line('    Providers: '.count($module->providers));
            }
        }

        $this->newLine();
        $this->info('Module discovery completed successfully.');

        return self::SUCCESS;
    }
}
