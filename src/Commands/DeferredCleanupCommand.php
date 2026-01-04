<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Illuminate\Console\Command;

/**
 * Clean up expired deferred API requests.
 *
 * This command removes deferred requests that have expired or been processed.
 * Scheduled to run daily to prevent database bloat.
 */
class DeferredCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blafast:deferred:cleanup
                            {--days=30 : Remove records older than this many days}
                            {--dry-run : Show what would be deleted without deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired deferred API requests';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Cleaning up deferred requests older than {$days} days...");

        // TODO: Implement when deferred API system is built
        // For now, this is a placeholder for the scheduler
        if ($dryRun) {
            $this->info('Dry run mode - no records deleted');
        } else {
            $this->info('Deferred cleanup completed (not yet implemented)');
        }

        return self::SUCCESS;
    }
}
