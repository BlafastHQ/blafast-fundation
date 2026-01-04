<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use Blafast\Foundation\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deferred Endpoint Configuration Model
 *
 * Defines which API endpoints can or must be executed asynchronously.
 *
 * @property string $id
 * @property string|null $organization_id
 * @property string $http_method
 * @property string $endpoint_pattern
 * @property bool $is_active
 * @property bool $force_deferred
 * @property string $priority
 * @property int $timeout
 * @property int $result_ttl
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class DeferredEndpointConfig extends Model
{
    use HasUuids;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'http_method',
        'endpoint_pattern',
        'is_active',
        'force_deferred',
        'priority',
        'timeout',
        'result_ttl',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'force_deferred' => 'boolean',
        'timeout' => 'integer',
        'result_ttl' => 'integer',
    ];

    /**
     * Get the organization that owns this config.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
