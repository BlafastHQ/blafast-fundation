<?php

declare(strict_types=1);

namespace Blafast\Foundation\Scopes;

use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * OrganizationScope Global Scope
 *
 * Automatically filters queries by organization_id based on the current OrganizationContext.
 * This ensures multi-tenant data isolation at the query level.
 */
class OrganizationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param Builder<Model> $builder
     * @param Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(OrganizationContext::class);

        // If in global context (superadmin mode), don't apply any filter
        if ($context->isGlobalContext()) {
            return;
        }

        // If an organization context is set, filter by organization_id
        if ($context->hasContext()) {
            $builder->where(
                $model->getTable().'.organization_id',
                $context->id()
            );
        }
    }
}
