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

        // Global context (superadmin/system): everything.
        if ($context->isGlobalContext()) {
            return;
        }

        // Org context: the org's notifications PLUS global/system rows (task 12) —
        // package notifications delivered by SendQueuedNotifications restore no org
        // context, so their organization_id is null; a strict equality filter
        // would hide them forever.
        if ($context->hasContext()) {
            $builder->where(function ($query) use ($context) {
                $query->where('organization_id', $context->id())
                    ->orWhereNull('organization_id');
            });

            return;
        }

        // FAIL CLOSED, keeping the global/system rows visible: with no context at
        // all, only null-org notifications remain readable.
        $builder->whereNull('organization_id');
    }
}
