<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Fixtures;

use Blafast\Foundation\Models\DatabaseNotification;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use Notifiable;

    /**
     * The package's whole permission runtime lives on the `api` guard. Without an
     * explicit guard, spatie falls back to config-order guard detection, and a
     * sanctum-authenticated request resolves `web` — every can()/hasPermissionTo()
     * then throws PermissionDoesNotExist for api-guard permissions (task 5).
     * Hosts must declare the same — see stubs/User.stub / docs/HOST-REQUIREMENTS.md.
     */
    protected $guard_name = 'api';

    /**
     * Route notifications through the package's org-scoped model (task 18):
     * Laravel's Notifiable resolves its own base DatabaseNotification and offers
     * no config to swap it — without this override the org scope, the
     * organization_id autofill and the column itself are dead code.
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest();
    }

    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Organizations that the user belongs to.
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->withPivot('role', 'is_active', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Check if user has permission in specific organization context.
     */
    public function hasOrganizationPermission(string $permission, Organization|string|null $organization = null): bool
    {
        if ($organization === null) {
            $context = app(OrganizationContext::class);
            $organization = $context->organization();
        }

        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $this->hasPermissionTo($permission, 'api', $organizationId);
    }

    /**
     * Check if user has role in specific organization context.
     */
    public function hasOrganizationRole(string $role, Organization|string|null $organization = null): bool
    {
        if ($organization === null) {
            $context = app(OrganizationContext::class);
            $organization = $context->organization();
        }

        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $this->hasRole($role, 'api', $organizationId);
    }

    /**
     * Check if user is a Superadmin.
     */
    public function isSuperadmin(): bool
    {
        return $this->hasRole('Superadmin', 'api');
    }
}
