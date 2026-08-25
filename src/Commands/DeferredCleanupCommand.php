<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Blafast\Foundation\Enums\DeferredRequestStatus;
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
                            {--days= : Remove records completed more than this many days ago (default: deferred.cleanup.older_than_days)}
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
        // Task 16: the previously dead cleanup config key is the default now.
        $days = (int) ($this->option('days') ?? config('blafast-fundation.deferred.cleanup.older_than_days', 7));

        if (! config('blafast-fundation.deferred.cleanup.enabled', true) && $this->option('days') === null) {
            $this->info('Deferred cleanup is disabled (deferred.cleanup.enabled).');

            return self::SUCCESS;
        }
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Cleaning up deferred requests older than {$days} days...");

        // Cross-organization maintenance, so the org scope is explicitly bypassed
        // (it fails closed with no context since task 12). Only TERMINAL rows are
        // deleted, anchored to COMPLETION time (M9): the old no-status-filter,
        // creation-anchored delete hard-removed in-flight rows during backlogs —
        // their queued jobs then died on ModelNotFoundException and poll links 404'd.
        $query = DeferredApiRequest::withoutOrganizationScope()
            ->whereIn('status', [
                DeferredRequestStatus::Completed,
                DeferredRequestStatus::Failed,
                DeferredRequestStatus::Cancelled,
            ])
            ->where('completed_at', '<', now()->subDays($days));

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
