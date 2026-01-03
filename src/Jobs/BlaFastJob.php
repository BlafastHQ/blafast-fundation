<?php

declare(strict_types=1);

namespace Blafast\Foundation\Jobs;

use Blafast\Foundation\Events\JobFailed;
use Blafast\Foundation\Jobs\Middleware\RestoreOrganizationContext;
use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Base job class for BlaFast jobs.
 *
 * All BlaFast jobs should extend this class to ensure:
 * - Jobs are queued for background processing
 * - Organization context is preserved and restored
 * - Failed jobs notify Superadmins after max attempts
 * - Consistent retry and timeout configuration
 */
abstract class BlaFastJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Organization ID for this job.
     */
    protected ?string $organizationId = null;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // Capture organization context at job creation time
        $context = app(OrganizationContext::class);
        if ($context->hasContext()) {
            $this->organizationId = $context->id();
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new RestoreOrganizationContext($this->organizationId),
        ];
    }

    /**
     * Handle a job failure.
     *
     * This method is called after the job has failed all retry attempts.
     */
    public function failed(Throwable $exception): void
    {
        if ($this->shouldNotifyOnFailure()) {
            $this->notifySuperadmins($exception);
        }
    }

    /**
     * Determine if superadmins should be notified on failure.
     */
    protected function shouldNotifyOnFailure(): bool
    {
        return config('blafast-fundation.queue.failed.notify_superadmins', true);
    }

    /**
     * Notify superadmins about the job failure.
     */
    protected function notifySuperadmins(Throwable $exception): void
    {
        event(new JobFailed($this, $exception));
    }
}
