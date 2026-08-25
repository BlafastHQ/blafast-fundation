<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Fixtures;

use Blafast\Foundation\Api\ApiMethodBuilder;
use Blafast\Foundation\Api\ApiStructureBuilder;
use Blafast\Foundation\Contracts\HasApiMethods;
use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Traits\ExposesApiMethods;
use Blafast\Foundation\Traits\ExposesApiStructure;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Multi-word fixture model (task 7 / audit H7): its class basename derives
 * DIFFERENT snake (`sales_order_model`) and kebab (`sales-order-model`) slugs,
 * which is exactly the divergence the slug unification must survive.
 */
class SalesOrderModel extends Model implements HasApiMethods, HasApiStructure
{
    use ExposesApiMethods;
    use ExposesApiStructure;
    use HasUuids;

    protected $table = 'test_sales_orders';

    protected $fillable = ['id', 'reference', 'approved'];

    protected $casts = ['approved' => 'boolean'];

    public static function apiStructure(): array
    {
        return ApiStructureBuilder::make(self::class)
            ->label('sales_orders.label')
            ->uuid('id', 'sales_orders.fields.id')
            // Deliberately not searchable/filterable: the filter pipeline crashes on
            // C5/H12 until task 10 lands, and this fixture exercises slugs, not filters.
            ->string('reference', 'sales_orders.fields.reference', searchable: false, sortable: true, filterable: false)
            ->build();
    }

    public static function apiMethods(): array
    {
        return [
            // Keyed by method slug explicitly — apiMethods() as a plain list gives the
            // registrar/controller numeric keys (exec.{slug}.0); task 27 normalizes it.
            'approve' => ApiMethodBuilder::make('approve', 'approve')->post()->build(),
        ];
    }

    public static function defaultRights(): array
    {
        return [
            'view' => ['Admin'],
            'list' => ['Admin'],
            'exec' => ['Admin' => ['*']],
        ];
    }

    public function approve(): array
    {
        $this->forceFill(['approved' => true])->save();

        return ['approved' => true];
    }
}
