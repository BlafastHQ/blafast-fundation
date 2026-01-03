<?php

declare(strict_types=1);

namespace Blafast\Foundation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Event fired when a job fails after all retry attempts.
 *
 * This event is used to notify superadmins about critical job failures.
 */
class JobFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly object $job,
        public readonly Throwable $exception,
    ) {}
}
