<?php

declare(strict_types=1);

namespace Blafast\Foundation\Jobs\Middleware;

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\OrganizationContext;

/**
 * Job middleware to restore organization context.
 *
 * Restores the organization context that was captured when the job was created,
 * ensuring that the job executes in the correct organization scope.
 */
class RestoreOrganizationContext
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private readonly ?string $organizationId
    ) {}

    /**
     * Process the queued job.
     */
    public function handle(object $job, callable $next): void
    {
        $context = app(OrganizationContext::class);

        if ($this->organizationId !== null) {
            $organization = Organization::find($this->organizationId);

            if ($organization === null) {
                // FAIL CLOSED (task 17/H9): the job was dispatched for an
                // organization that no longer resolves — it must not run at all.
                // (Under task 12's fail-closed scope it would otherwise read
                // silent EMPTY result sets: safer than the old all-tenants leak,
                // but just as wrong.) Deletion is permanent ⇒ fail, not release.
                $exception = new \RuntimeException(
                    "Organization [{$this->organizationId}] no longer exists; the job cannot run in its context."
                );

                if (method_exists($job, 'fail')) {
                    $job->fail($exception);

                    return;
                }

                throw $exception;
            }

            // Explicit job-context API — replaces the old reflection write that
            // bypassed set()'s invariants (task 17). Also syncs the team id.
            $context->setForJob($organization);
        }

        try {
            $next($job);
        } finally {
            // Clear organization context after job execution — clear() also resets
            // the spatie team id, so a worker never leaks it into the next job.
            $context->clear();
        }
    }
}
