<?php

declare(strict_types=1);

namespace Blafast\Foundation\Policies;

use Blafast\Foundation\Models\SystemSetting;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for SystemSetting model authorization.
 *
 * Only Superadmins can manage system settings.
 */
class SystemSettingPolicy
{
    /**
     * Determine whether the user can view any system settings.
     */
    public function viewAny(Authenticatable $user): bool
    {
        // Check if user has Superadmin role (if method exists)
        if (method_exists($user, 'hasRole') && $user->hasRole('Superadmin')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the system setting.
     */
    public function view(Authenticatable $user, SystemSetting $systemSetting): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create system settings.
     */
    public function create(Authenticatable $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the system setting.
     */
    public function update(Authenticatable $user, ?SystemSetting $systemSetting = null): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can delete the system setting.
     */
    public function delete(Authenticatable $user, ?SystemSetting $systemSetting = null): bool
    {
        return $this->viewAny($user);
    }
}
