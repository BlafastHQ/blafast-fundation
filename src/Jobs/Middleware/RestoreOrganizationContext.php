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

        // Restore organization context if we have an organization ID
        if ($this->organizationId) {
            $organization = Organization::find($this->organizationId);
            if ($organization) {
                // Manually set organization context for job execution
                // Jobs don't have a user context, so we use reflection to set just the organization
                $reflection = new \ReflectionClass($context);
                $orgProperty = $reflection->getProperty('organization');
                $orgProperty->setAccessible(true);
                $orgProperty->setValue($context, $organization);
            }
        }

        try {
            $next($job);
        } finally {
            // Clear organization context after job execution
            $context->clear();
        }
    }
}
