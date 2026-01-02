<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Fixtures;

use Blafast\Foundation\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Test model for testing the BelongsToOrganization trait and OrganizationScope.
 */
class TenantModel extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    protected $fillable = [
        'name',
        'organization_id',
    ];
}
