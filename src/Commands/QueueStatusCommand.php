<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Display queue status for all configured queues.
 *
 * Shows pending and failed job counts for each queue to help
 * monitor queue health and identify bottlenecks.
 */
class QueueStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blafast:queue:status
                            {--all : Show all queues, not just configured ones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show queue status for all configured queues';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queues = $this->getQueues();

        if (empty($queues)) {
            $this->info('No queues configured.');

            return Command::SUCCESS;
        }

        $data = [];

        foreach ($queues as $queue) {
            $data[] = [
                'queue' => $queue,
                'pending' => $this->getPendingCount($queue),
                'failed' => $this->getFailedCount($queue),
            ];
        }

        $this->table(
            ['Queue', 'Pending', 'Failed'],
            $data
        );

        // Show summary
        $totalPending = array_sum(array_column($data, 'pending'));
        $totalFailed = array_sum(array_column($data, 'failed'));

        $this->newLine();
        $this->info("Total Pending: {$totalPending}");
        $this->info("Total Failed: {$totalFailed}");

        return Command::SUCCESS;
    }

    /**
     * Get the list of queues to display.
     *
     * @return array<int, string>
     */
    protected function getQueues(): array
    {
        if ($this->option('all')) {
            // Get all unique queue names from jobs and failed_jobs tables
            $jobQueues = DB::table('jobs')
                ->select('queue')
                ->distinct()
                ->pluck('queue')
                ->toArray();

            $failedQueues = DB::table('failed_jobs')
                ->select('queue')
                ->distinct()
                ->pluck('queue')
                ->toArray();

            return array_unique(array_merge($jobQueues, $failedQueues));
        }

        // Return configured queues
        return array_values(config('blafast-fundation.queue.names', []));
    }

    /**
     * Get the count of pending jobs for a queue.
     */
    protected function getPendingCount(string $queue): int
    {
        return DB::table('jobs')
            ->where('queue', $queue)
            ->count();
    }

    /**
     * Get the count of failed jobs for a queue.
     */
    protected function getFailedCount(string $queue): int
    {
        return DB::table('failed_jobs')
            ->where('queue', $queue)
            ->count();
    }
}
