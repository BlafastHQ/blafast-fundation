<?php

declare(strict_types=1);

namespace Blafast\Foundation\Policies;

use Blafast\Foundation\Models\Activity;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for Activity model authorization.
 *
 * Controls access to activity log entries. Only Superadmins and users with
 * the 'view_activity_log' permission can access activity logs.
 */
class ActivityPolicy
{
    /**
     * Determine whether the user can view any activities.
     */
    public function viewAny(Authenticatable $user): bool
    {
        // Check if user has Superadmin role (if method exists)
        if (method_exists($user, 'hasRole') && $user->hasRole('Superadmin')) {
            return true;
        }

        // Check for view_activity_log permission
        return $user->can('view_activity_log');
    }

    /**
     * Determine whether the user can view the activity.
     */
    public function view(Authenticatable $user, Activity $activity): bool
    {
        return $this->viewAny($user);
    }
}
