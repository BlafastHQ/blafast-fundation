<?php

declare(strict_types=1);

namespace Blafast\Foundation\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Check scheduler health and last run times.
 *
 * Verifies that the scheduler is running properly by checking
 * the heartbeat file and displaying upcoming scheduled tasks.
 */
class SchedulerHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blafast:scheduler:health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check scheduler health and last run times';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $heartbeatFile = storage_path('framework/schedule-heartbeat');

        if (! file_exists($heartbeatFile)) {
            $this->error('Scheduler has never run. Check cron configuration.');
            $this->newLine();
            $this->warn('Add this to your crontab:');
            $this->line('* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');

            return self::FAILURE;
        }

        $lastRun = Carbon::createFromTimestamp(filemtime($heartbeatFile));
        $minutesAgo = $lastRun->diffInMinutes(now());

        if ($minutesAgo > 5) {
            $this->error("Scheduler last ran {$minutesAgo} minutes ago. May be stuck.");
            $this->line("Last heartbeat: {$lastRun->toDateTimeString()}");

            return self::FAILURE;
        }

        $this->info('✓ Scheduler healthy');
        $this->line("Last heartbeat: {$lastRun->diffForHumans()} ({$lastRun->toDateTimeString()})");
        $this->newLine();

        // List upcoming tasks
        $this->info('Upcoming scheduled tasks:');
        $this->call('schedule:list');

        return self::SUCCESS;
    }
}
