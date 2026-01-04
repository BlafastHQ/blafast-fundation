<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Blafast\Foundation\Models\DeferredApiRequest;
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

        // Find expired requests
        $query = DeferredApiRequest::where('expires_at', '<', now())
            ->orWhere('created_at', '<', now()->subDays($days));

        $count = $query->count();

        if ($count === 0) {
            $this->info('No expired deferred requests found.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Would delete {$count} expired deferred requests (dry run mode)");

            return self::SUCCESS;
        }

        // Delete expired requests
        $deleted = $query->delete();

        $this->info("Deleted {$deleted} expired deferred requests.");

        return self::SUCCESS;
    }
}
