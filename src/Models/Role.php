<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;
    use HasUuids;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'organization_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'organization_id' => 'string',
    ];

    /**
     * The organization ID associated with this role.
     */
    public ?string $organization_id = null;

    /**
     * Get the organization that owns the role.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope a query to global roles (no organization).
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('organization_id');
    }

    /**
     * Scope a query to organization-specific roles.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Check if this is a global role (like Superadmin).
     */
    public function isGlobal(): bool
    {
        return $this->organization_id === null;
    }

    /**
     * Check if this is the Superadmin role.
     */
    public function isSuperadmin(): bool
    {
        return $this->name === 'Superadmin' && $this->isGlobal();
    }
}
