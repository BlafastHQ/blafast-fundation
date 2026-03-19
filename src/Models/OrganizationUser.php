<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * OrganizationUser Pivot Model
 *
 * Represents the membership of a user in an organization.
 *
 * @property string $id
 * @property string $user_id
 * @property string $organization_id
 * @property string $role
 * @property bool $is_active
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Organization $organization
 *
 * @method static Builder|OrganizationUser active()
 * @method static Builder|OrganizationUser byRole(string $role)
 */
class OrganizationUser extends Pivot
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'organization_user';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns this membership.
     *
     * @return BelongsTo<User, OrganizationUser>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the organization that owns this membership.
     *
     * @return BelongsTo<Organization, OrganizationUser>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope a query to only include active memberships.
     *
     * @param  Builder<OrganizationUser>  $query
     * @return Builder<OrganizationUser>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('left_at');
    }

    /**
     * Scope a query to filter by role.
     *
     * @param  Builder<OrganizationUser>  $query
     * @return Builder<OrganizationUser>
     */
    public function scopeByRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    /**
     * Check if the membership is active.
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->left_at === null;
    }

    /**
     * Get a metadata value by key with optional default.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Set a metadata value by key.
     */
    public function setMetadata(string $key, mixed $value): self
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;

        return $this;
    }
}
