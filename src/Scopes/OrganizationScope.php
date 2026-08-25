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
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(OrganizationContext::class);

        // Global context (superadmin/system mode): no filter.
        if ($context->isGlobalContext()) {
            return;
        }

        // Organization context: filter by organization_id.
        if ($context->hasContext()) {
            $builder->where(
                $model->getTable().'.organization_id',
                $context->id()
            );

            return;
        }

        // FAIL CLOSED (task 12): no context — in ANY runtime — means zero rows,
        // never a silent full table. Legitimate cross-org paths must opt out via
        // Model::withoutOrganizationScope() or OrganizationContext::
        // setGlobalContext()/runAsSystem().
        $builder->whereRaw('1 = 0');
    }
}
