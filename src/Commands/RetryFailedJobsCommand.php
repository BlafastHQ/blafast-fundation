<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Retry failed jobs with filtering options.
 *
 * Provides a convenient way to retry failed jobs filtered by
 * queue name and time range.
 */
class RetryFailedJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blafast:queue:retry
                            {--queue= : Retry jobs for specific queue}
                            {--hours=24 : Only retry jobs failed within this many hours}
                            {--all : Retry all failed jobs without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed jobs with filtering options';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = DB::table('failed_jobs');

        // Filter by queue if specified
        if ($queue = $this->option('queue')) {
            $query->where('queue', $queue);
            $this->info("Filtering by queue: {$queue}");
        }

        // Filter by time range
        $hours = (int) $this->option('hours');
        $query->where('failed_at', '>=', now()->subHours($hours));
        $this->info("Filtering jobs failed within last {$hours} hours");

        $count = $query->count();

        if ($count === 0) {
            $this->info('No failed jobs match the criteria.');

            return Command::SUCCESS;
        }

        // Get UUIDs of jobs to retry
        $uuids = $query->pluck('uuid');

        $this->newLine();
        $this->info("Found {$count} failed jobs to retry");

        // Confirm before retrying unless --all is specified
        if (! $this->option('all') && ! $this->confirm("Retry {$count} failed jobs?", true)) {
            $this->info('Retry cancelled.');

            return Command::SUCCESS;
        }

        // Retry each job
        $successCount = 0;
        $failureCount = 0;

        $this->withProgressBar($uuids, function ($uuid) use (&$successCount, &$failureCount) {
            try {
                Artisan::call('queue:retry', ['id' => $uuid]);
                $successCount++;
            } catch (\Throwable $e) {
                $failureCount++;
                $this->error("Failed to retry job {$uuid}: {$e->getMessage()}");
            }
        });

        $this->newLine(2);
        $this->info("Successfully queued {$successCount} jobs for retry.");

        if ($failureCount > 0) {
            $this->error("Failed to queue {$failureCount} jobs.");
        }

        return Command::SUCCESS;
    }
}
