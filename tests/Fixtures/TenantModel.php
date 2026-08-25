<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Fixtures;

use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Traits\BelongsToOrganization;
use Blafast\Foundation\Traits\ExposesApiStructure;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Test model for testing the BelongsToOrganization trait and OrganizationScope.
 * Also exposes an API structure so task 12 can prove the secure-by-default
 * macro on an organization-scoped model.
 */
class TenantModel extends Model implements HasApiStructure
{
    use BelongsToOrganization;
    use ExposesApiStructure;
    use HasUuids;

    protected $fillable = [
        'name',
        'organization_id',
    ];

    public static function apiStructure(): array
    {
        return [
            'slug' => 'tenant-model',
            'label' => 'Tenant',
            'fields' => [
                ['name' => 'id', 'label' => 'ID', 'type' => 'uuid'],
                ['name' => 'name', 'label' => 'Name', 'type' => 'string', 'sortable' => true],
            ],
        ];
    }
}
