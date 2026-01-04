<?php

declare(strict_types=1);

namespace Blafast\Foundation\Events;

use Illuminate\Console\Scheduling\Schedule;

/**
 * Event fired to allow modules to register their scheduled tasks.
 *
 * Modules can listen to this event to add their own scheduled tasks
 * to the Laravel scheduler.
 */
class RegisterScheduledTasks
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public Schedule $schedule
    ) {}
}
