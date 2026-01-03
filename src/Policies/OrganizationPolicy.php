<?php

declare(strict_types=1);

namespace Blafast\Foundation\Policies;

use Blafast\Foundation\Models\Organization;
use Illuminate\Contracts\Auth\Authenticatable;

class OrganizationPolicy
{
    /**
     * Determine whether the user can view any organizations.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('list_organizations');
    }

    /**
     * Determine whether the user can view the organization.
     */
    public function view(Authenticatable $user, Organization $organization): bool
    {
        return $user->can('view_organizations');
    }

    /**
     * Determine whether the user can create organizations.
     */
    public function create(Authenticatable $user): bool
    {
        return $user->can('create_organizations');
    }

    /**
     * Determine whether the user can update the organization.
     */
    public function update(Authenticatable $user, Organization $organization): bool
    {
        return $user->can('update_organizations');
    }

    /**
     * Determine whether the user can delete the organization.
     */
    public function delete(Authenticatable $user, Organization $organization): bool
    {
        return $user->can('delete_organizations');
    }
}
