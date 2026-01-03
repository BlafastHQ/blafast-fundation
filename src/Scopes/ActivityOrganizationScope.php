<?php

declare(strict_types=1);

namespace Blafast\Foundation\Scopes;

use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope for filtering activities by organization.
 *
 * Ensures that activities are automatically filtered by the current
 * organization context, providing data isolation for multi-tenant systems.
 *
 * Superadmins in global context can see all activities.
 */
class ActivityOrganizationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(OrganizationContext::class);

        // Superadmins in global context can see all
        if ($context->isGlobalContext()) {
            return;
        }

        // Filter by organization if context exists
        if ($context->hasContext()) {
            $builder->where('organization_id', $context->id());
        }
    }
}
