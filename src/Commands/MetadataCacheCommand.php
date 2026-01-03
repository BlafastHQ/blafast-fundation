<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\ModelRegistry;
use Illuminate\Console\Command;

/**
 * Artisan command for managing metadata cache.
 *
 * Actions:
 * - warm: Pre-populate cache for all models
 * - clear: Invalidate cache entries
 * - status: Show cache configuration
 */
class MetadataCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blafast:cache:metadata
        {action : Action to perform (warm|clear|status)}
        {--model= : Specific model slug to target}
        {--organization= : Specific organization ID to target}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage metadata cache (warm, clear, or check status)';

    /**
     * Execute the console command.
     */
    public function handle(
        MetadataCacheService $cache,
        ModelRegistry $registry
    ): int {
        $action = $this->argument('action');

        return match ($action) {
            'warm' => $this->warm($cache, $registry),
            'clear' => $this->clear($cache),
            'status' => $this->status($cache, $registry),
            default => $this->invalidAction($action),
        };
    }

    /**
     * Warm metadata cache.
     */
    protected function warm(MetadataCacheService $cache, ModelRegistry $registry): int
    {
        $this->info('Warming metadata cache...');

        if ($model = $this->option('model')) {
            // Warm specific model
            try {
                $modelClass = $registry->resolve($model);
                $cache->warmModel($modelClass);
                $this->info("✓ Warmed cache for model: {$model}");
            } catch (\Exception $e) {
                $this->error("✗ Failed to warm cache for {$model}: {$e->getMessage()}");

                return Command::FAILURE;
            }
        } else {
            // Warm all models
            $models = $registry->all();
            $count = count($models);

            $this->info("Warming cache for {$count} models...");

            $bar = $this->output->createProgressBar($count);
            $bar->start();

            foreach ($models as $modelClass) {
                try {
                    $cache->warmModel($modelClass);
                    $bar->advance();
                } catch (\Exception $e) {
                    $this->newLine();
                    $this->warn("Failed to warm {$modelClass}: {$e->getMessage()}");
                }
            }

            $bar->finish();
            $this->newLine();
            $this->info('✓ Warmed cache for all models');
        }

        return Command::SUCCESS;
    }

    /**
     * Clear metadata cache.
     */
    protected function clear(MetadataCacheService $cache): int
    {
        $this->info('Clearing metadata cache...');

        if ($model = $this->option('model')) {
            // Clear specific model
            $cache->invalidateModel($model);
            $this->info("✓ Cleared cache for model: {$model}");
        } elseif ($org = $this->option('organization')) {
            // Clear specific organization
            $cache->invalidateOrganization($org);
            $this->info("✓ Cleared cache for organization: {$org}");
        } else {
            // Clear all metadata cache
            if ($this->confirm('Clear ALL metadata cache?', false)) {
                $cache->invalidateAll();
                $this->info('✓ Cleared all metadata cache');
            } else {
                $this->info('Cancelled');

                return Command::SUCCESS;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Show cache status.
     */
    protected function status(MetadataCacheService $cache, ModelRegistry $registry): int
    {
        $stats = $cache->getStats();

        $this->info('Metadata Cache Status');
        $this->line('─────────────────────');

        $this->table(
            ['Setting', 'Value'],
            [
                ['Cache Driver', $stats['driver']],
                ['Supports Tagging', $stats['supports_tagging'] ? 'Yes' : 'No'],
                ['TTL', "{$stats['ttl']} seconds"],
                ['Prefix', $stats['prefix']],
                ['Registered Models', count($registry->all())],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Handle invalid action.
     */
    protected function invalidAction(string $action): int
    {
        $this->error("Invalid action '{$action}'. Use: warm, clear, or status");

        return Command::FAILURE;
    }
}
