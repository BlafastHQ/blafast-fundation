<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * OrganizationContext Service
 *
 * Per-request singleton that manages the current organization context for multi-tenant data isolation.
 * This service is scoped to the request lifecycle and is automatically flushed on new requests.
 */
class OrganizationContext
{
    /**
     * The current organization context.
     */
    private ?Organization $organization = null;

    /**
     * The current authenticated user.
     */
    private ?object $user = null;

    /**
     * Whether the context is in global mode (superadmin bypass).
     */
    private bool $isGlobalContext = false;

    /**
     * Set the organization context for the current request.
     */
    public function set(Organization $organization, object $user): void
    {
        // Validate that the user belongs to this organization
        if (! $this->validateUserBelongsToOrganization($user, $organization)) {
            throw new \RuntimeException(
                "User {$user->id} does not belong to organization {$organization->id}"
            );
        }

        $this->organization = $organization;
        $this->user = $user;
        $this->isGlobalContext = false;

        // C1: spatie permission runs in teams mode — every hasRole()/can() filters by
        // the registrar's team id. It MUST follow the org context, or all org-scoped
        // grants resolve against team NULL (global) and deny every member.
        $this->syncPermissionsTeamId($organization->id, $user);

        Log::debug('Organization context set', [
            'organization_id' => $organization->id,
            'organization_slug' => $organization->slug,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set global context mode (superadmin bypass).
     * This removes the organization filter from all queries.
     */
    public function setGlobalContext(?object $superadmin = null): void
    {
        $this->organization = null;
        $this->user = $superadmin;
        $this->isGlobalContext = true;

        // Global context is team NULL — correct because only global (NULL-team)
        // roles like Superadmin should apply here.
        $this->syncPermissionsTeamId(null, $superadmin);

        Log::warning('Global organization context enabled', [
            'user_id' => $superadmin->id ?? 'system',
        ]);
    }

    /**
     * Set the organization context for a QUEUED JOB (task 17): jobs have no
     * authenticated user, so `user()` is deliberately null here — the one state
     * where hasContext() true + a null user is by design. Replaces the old
     * reflection write into the private property, and keeps the spatie team id
     * in sync (task 5).
     */
    public function setForJob(Organization $organization): void
    {
        $this->organization = $organization;
        $this->user = null;
        $this->isGlobalContext = false;

        $this->syncPermissionsTeamId($organization->id);

        Log::debug('Organization context set for job', [
            'organization_id' => $organization->id,
        ]);
    }

    /**
     * Run a callback in USER-LESS global context (task 12): the escape hatch for
     * seeders, scheduled commands and migrations now that OrganizationScope fails
     * closed — with no context at all, scoped queries return zero rows.
     * The previous context is restored afterwards.
     */
    public function runAsSystem(callable $callback): mixed
    {
        $previousOrg = $this->organization;
        $previousUser = $this->user;
        $previousGlobal = $this->isGlobalContext;

        try {
            $this->setGlobalContext();

            return $callback();
        } finally {
            $this->organization = $previousOrg;
            $this->user = $previousUser;
            $this->isGlobalContext = $previousGlobal;
            $this->syncPermissionsTeamId($previousGlobal ? null : $previousOrg?->id, $previousUser);
        }
    }

    /**
     * Clear the current organization context.
     */
    public function clear(): void
    {
        $previousUser = $this->user;

        $this->organization = null;
        $this->user = null;
        $this->isGlobalContext = false;

        $this->syncPermissionsTeamId(null, $previousUser);

        Log::debug('Organization context cleared');
    }

    /**
     * Get the current organization ID.
     */
    public function id(): ?string
    {
        return $this->organization?->id;
    }

    /**
     * Get the current organization slug.
     */
    public function slug(): ?string
    {
        return $this->organization?->slug;
    }

    /**
     * Get the current organization instance.
     */
    public function organization(): ?Organization
    {
        return $this->organization;
    }

    /**
     * Get the current authenticated user.
     */
    public function user(): ?object
    {
        return $this->user;
    }

    /**
     * Check if the context is in global mode (superadmin bypass).
     */
    public function isGlobalContext(): bool
    {
        return $this->isGlobalContext;
    }

    /**
     * Check if an organization context has been set.
     */
    public function hasContext(): bool
    {
        return $this->organization !== null && ! $this->isGlobalContext;
    }

    /**
     * Get the cache tag for the current organization.
     */
    public function cacheTag(): string
    {
        if (! $this->hasContext()) {
            throw new \RuntimeException('No organization context is set');
        }

        return "organization:{$this->organization->id}";
    }

    /**
     * Get all cache tags for the current organization.
     * Returns an array with both ID-based and slug-based tags.
     *
     * @return array<int, string>
     */
    public function cacheTags(): array
    {
        if (! $this->hasContext()) {
            throw new \RuntimeException('No organization context is set');
        }

        return [
            "organization:{$this->organization->id}",
            "organization-slug:{$this->organization->slug}",
        ];
    }

    /**
     * Validate that a user belongs to the specified organization.
     */
    public function validateUserBelongsToOrganization(object $user, Organization $organization): bool
    {
        return $organization->hasUser($user);
    }

    /**
     * Require an organization context to be set.
     * Throws an exception if no context is available.
     *
     * @throws \RuntimeException
     */
    public function require(): Organization
    {
        if (! $this->hasContext()) {
            throw new \RuntimeException('No organization context is set');
        }

        return $this->organization;
    }

    /**
     * Execute a callback with a specific organization context.
     * The context is restored to its previous state after the callback.
     */
    public function with(Organization $organization, object $user, callable $callback): mixed
    {
        $previousOrg = $this->organization;
        $previousUser = $this->user;
        $previousGlobal = $this->isGlobalContext;

        try {
            $this->set($organization, $user);

            return $callback();
        } finally {
            $this->organization = $previousOrg;
            $this->user = $previousUser;
            $this->isGlobalContext = $previousGlobal;
            $this->syncPermissionsTeamId($previousGlobal ? null : $previousOrg?->id, $previousUser);
        }
    }

    /**
     * Execute a callback in global context (superadmin mode).
     * The context is restored to its previous state after the callback.
     */
    public function withGlobalContext(object $superadmin, callable $callback): mixed
    {
        $previousOrg = $this->organization;
        $previousUser = $this->user;
        $previousGlobal = $this->isGlobalContext;

        try {
            $this->setGlobalContext($superadmin);

            return $callback();
        } finally {
            $this->organization = $previousOrg;
            $this->user = $previousUser;
            $this->isGlobalContext = $previousGlobal;
            $this->syncPermissionsTeamId($previousGlobal ? null : $previousOrg?->id, $previousUser);
        }
    }

    /**
     * Keep spatie permission's team id in lockstep with the organization context
     * (C1). Also drops the memoized roles/permissions relations on the affected
     * user: spatie caches them per model instance, so a collection loaded under
     * another team would otherwise keep answering hasRole()/can() stale.
     */
    private function syncPermissionsTeamId(?string $organizationId, ?object $user = null): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($organizationId);

        if ($user instanceof Model) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
