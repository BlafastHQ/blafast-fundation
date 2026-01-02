<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\OrganizationContext;

if (! function_exists('organization')) {
    /**
     * Get the current organization from the context.
     */
    function organization(): ?Organization
    {
        return app(OrganizationContext::class)->organization();
    }
}

if (! function_exists('organization_id')) {
    /**
     * Get the current organization ID from the context.
     */
    function organization_id(): ?string
    {
        return app(OrganizationContext::class)->id();
    }
}

if (! function_exists('organization_slug')) {
    /**
     * Get the current organization slug from the context.
     */
    function organization_slug(): ?string
    {
        return app(OrganizationContext::class)->slug();
    }
}

if (! function_exists('organization_context')) {
    /**
     * Get the OrganizationContext service instance.
     */
    function organization_context(): OrganizationContext
    {
        return app(OrganizationContext::class);
    }
}

if (! function_exists('has_organization_context')) {
    /**
     * Check if an organization context is currently set.
     */
    function has_organization_context(): bool
    {
        return app(OrganizationContext::class)->hasContext();
    }
}

if (! function_exists('is_global_organization_context')) {
    /**
     * Check if the current context is in global mode (superadmin).
     */
    function is_global_organization_context(): bool
    {
        return app(OrganizationContext::class)->isGlobalContext();
    }
}
