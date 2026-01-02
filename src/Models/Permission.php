<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
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
     * Get the organization that owns the permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Blafast\Foundation\Models\Organization, $this>
     */
    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope a query to global permissions (no organization).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Permission>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Permission>
     */
    public function scopeGlobal(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('organization_id');
    }

    /**
     * Scope a query to organization-specific permissions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Permission>  $query
     * @param  string  $organizationId
     * @return \Illuminate\Database\Eloquent\Builder<Permission>
     */
    public function scopeForOrganization(\Illuminate\Database\Eloquent\Builder $query, string $organizationId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
