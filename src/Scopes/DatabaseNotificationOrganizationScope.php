<?php

declare(strict_types=1);

namespace Blafast\Foundation\Scopes;

use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope for filtering notifications by organization context.
 *
 * Automatically applies organization_id filter to all queries, ensuring
 * users only see notifications for their current organization.
 * Superadmins in global context can see all notifications.
 */
class DatabaseNotificationOrganizationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(OrganizationContext::class);

        // Only apply scope if we have an organization context
        if ($context->hasContext()) {
            $builder->where('organization_id', $context->id());
        }
    }
}
