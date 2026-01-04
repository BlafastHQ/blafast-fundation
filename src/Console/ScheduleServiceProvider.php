<?php

declare(strict_types=1);

namespace Blafast\Foundation\Console;

use Blafast\Foundation\Events\RegisterScheduledTasks;
use Blafast\Foundation\Notifications\ScheduledTaskFailedNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for registering scheduled tasks.
 *
 * Configures all recurring tasks for the BlaFast Foundation package
 * and provides hooks for modules to register their own schedules.
 */
class ScheduleServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the scheduled tasks.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $this->registerScheduledTasks($schedule);
        });
    }

    /**
     * Register all scheduled tasks.
     */
    protected function registerScheduledTasks(Schedule $schedule): void
    {
        // Scheduler heartbeat - runs every minute to verify scheduler is working
        $schedule->call(function () {
            $heartbeatPath = storage_path('framework/schedule-heartbeat');
            $directory = dirname($heartbeatPath);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            touch($heartbeatPath);
        })->everyMinute()->description('Scheduler heartbeat');

        // Activity log cleanup - daily at 2 AM
        $schedule->command('blafast:activity:cleanup')
            ->daily()
            ->at('02:00')
            ->description('Clean up old activity log entries')
            ->onFailure(fn () => $this->notifyFailure('Activity log cleanup failed'))
            ->runInBackground();

        // Deferred requests cleanup - daily at 3 AM
        $schedule->command('blafast:deferred:cleanup')
            ->daily()
            ->at('03:00')
            ->description('Clean up expired deferred requests')
            ->onFailure(fn () => $this->notifyFailure('Deferred cleanup failed'))
            ->runInBackground();

        // Cache cleanup - daily at 4 AM
        $schedule->command('cache:prune-stale-tags')
            ->daily()
            ->at('04:00')
            ->description('Prune stale cache tags')
            ->runInBackground();

        // Metadata cache warming - daily at 5 AM
        $schedule->command('blafast:cache:metadata warm')
            ->daily()
            ->at('05:00')
            ->description('Warm metadata caches')
            ->onFailure(fn () => $this->notifyFailure('Metadata cache warming failed'))
            ->runInBackground();

        // Module discovery refresh - weekly on Sunday at 1 AM
        $schedule->command('blafast:modules:discover')
            ->weekly()
            ->sundays()
            ->at('01:00')
            ->description('Refresh module discovery cache')
            ->onFailure(fn () => $this->notifyFailure('Module discovery failed'))
            ->runInBackground();

        // Allow modules to register their schedules
        $this->registerModuleSchedules($schedule);
    }

    /**
     * Allow modules to register their own scheduled tasks.
     */
    protected function registerModuleSchedules(Schedule $schedule): void
    {
        // Fire event for modules to register schedules
        event(new RegisterScheduledTasks($schedule));
    }

    /**
     * Notify Superadmins when a scheduled task fails.
     */
    protected function notifyFailure(string $message): void
    {
        // Log the failure
        Log::error("Scheduled task failed: {$message}");

        try {
            // Get Superadmin users if Spatie Permission package is installed
            if (class_exists(\Spatie\Permission\Models\Role::class)) {
                // @phpstan-ignore staticMethod.notFound
                $superadmins = \App\Models\User::role('Superadmin')->get();

                if ($superadmins->isNotEmpty()) {
                    Notification::send($superadmins, new ScheduledTaskFailedNotification($message));
                }
            }
        } catch (\Throwable $e) {
            // Log but don't throw - we don't want to break the scheduler
            Log::error("Failed to send scheduled task failure notification: {$e->getMessage()}");
        }
    }
}
