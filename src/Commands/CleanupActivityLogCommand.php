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
        {--days=365 : Delete records older than this many days}';

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
        $days = (int) $this->option('days');

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
