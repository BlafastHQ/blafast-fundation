<?php

declare(strict_types=1);

namespace Blafast\Foundation\Jobs\Middleware;

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\OrganizationContext;
use Spatie\Permission\PermissionRegistrar;

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
                // (task 17 replaces this with an explicit setForJob() API and makes the
                // missing-organization path fail closed).
                $reflection = new \ReflectionClass($context);
                $orgProperty = $reflection->getProperty('organization');
                $orgProperty->setAccessible(true);
                $orgProperty->setValue($context, $organization);

                // C1: the reflection write bypasses set(), so sync the spatie team id
                // explicitly — org-scoped permission checks inside the job depend on it.
                app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
            }
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
