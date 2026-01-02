<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Models\Organization;
use Illuminate\Support\Facades\Log;

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
     *
     * @var Organization|null
     */
    private ?Organization $organization = null;

    /**
     * The current authenticated user.
     *
     * @var object|null
     */
    private ?object $user = null;

    /**
     * Whether the context is in global mode (superadmin bypass).
     *
     * @var bool
     */
    private bool $isGlobalContext = false;

    /**
     * Set the organization context for the current request.
     *
     * @param Organization $organization
     * @param object $user
     * @return void
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

        Log::debug('Organization context set', [
            'organization_id' => $organization->id,
            'organization_slug' => $organization->slug,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set global context mode (superadmin bypass).
     * This removes the organization filter from all queries.
     *
     * @param object $superadmin
     * @return void
     */
    public function setGlobalContext(object $superadmin): void
    {
        $this->organization = null;
        $this->user = $superadmin;
        $this->isGlobalContext = true;

        Log::warning('Global organization context enabled', [
            'user_id' => $superadmin->id,
        ]);
    }

    /**
     * Clear the current organization context.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->organization = null;
        $this->user = null;
        $this->isGlobalContext = false;

        Log::debug('Organization context cleared');
    }

    /**
     * Get the current organization ID.
     *
     * @return string|null
     */
    public function id(): ?string
    {
        return $this->organization?->id;
    }

    /**
     * Get the current organization slug.
     *
     * @return string|null
     */
    public function slug(): ?string
    {
        return $this->organization?->slug;
    }

    /**
     * Get the current organization instance.
     *
     * @return Organization|null
     */
    public function organization(): ?Organization
    {
        return $this->organization;
    }

    /**
     * Get the current authenticated user.
     *
     * @return object|null
     */
    public function user(): ?object
    {
        return $this->user;
    }

    /**
     * Check if the context is in global mode (superadmin bypass).
     *
     * @return bool
     */
    public function isGlobalContext(): bool
    {
        return $this->isGlobalContext;
    }

    /**
     * Check if an organization context has been set.
     *
     * @return bool
     */
    public function hasContext(): bool
    {
        return $this->organization !== null && ! $this->isGlobalContext;
    }

    /**
     * Get the cache tag for the current organization.
     *
     * @return string
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
     *
     * @param object $user
     * @param Organization $organization
     * @return bool
     */
    public function validateUserBelongsToOrganization(object $user, Organization $organization): bool
    {
        return $organization->hasUser($user);
    }

    /**
     * Require an organization context to be set.
     * Throws an exception if no context is available.
     *
     * @return Organization
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
     *
     * @param Organization $organization
     * @param object $user
     * @param callable $callback
     * @return mixed
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
        }
    }

    /**
     * Execute a callback in global context (superadmin mode).
     * The context is restored to its previous state after the callback.
     *
     * @param object $superadmin
     * @param callable $callback
     * @return mixed
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
        }
    }
}
