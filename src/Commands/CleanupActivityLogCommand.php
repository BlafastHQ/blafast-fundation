<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Blafast\Foundation\Models\Activity;
use Illuminate\Console\Command;

/**
 * Command to clean up old activity log entries.
 *
 * Usage:
 * ```
 * php artisan blafast:activity:cleanup
 * php artisan blafast:activity:cleanup --days=90
 * ```
 */
class CleanupActivityLogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blafast:activity:cleanup
        {--days= : Delete records older than this many days (default: activity_log.retention_days)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old activity log entries';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Task 23: activity_log.retention_days is the default now — it said 90
        // while the command hardcoded 365 and the scheduler passed no override.
        $days = (int) ($this->option('days') ?? config('blafast-fundation.activity_log.retention_days', 365));

        if ($days < 1) {
            $this->error('Days must be a positive number.');

            return Command::FAILURE;
        }

        $this->info("Deleting activity log entries older than {$days} days...");

        // Delete old entries without organization scope
        $deleted = Activity::withoutOrganizationScope()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("✓ Deleted {$deleted} activity log entries.");

        return Command::SUCCESS;
    }
}
