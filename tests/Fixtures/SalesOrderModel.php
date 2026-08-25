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
use Illuminate\Http\UploadedFile;

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
            // Task 26 fixtures: every ApiMethodParameterType + the H16/H17 traps.
            'print' => ApiMethodBuilder::make('print', 'printLabels')->get()
                ->optionalParam('copies', 'integer')
                ->build(),
            'notify' => ApiMethodBuilder::make('notify', 'notifyPeople')->post()
                ->requiredParam('emails', 'array:email')
                ->optionalParam('at', 'array:datetime')
                ->optionalParam('weights', 'array:float')
                ->build(),
            // Declared order (carrier, boxes) deliberately DIFFERS from the PHP
            // signature ship(int $boxes, string $carrier) — positional binding
            // misbinds this pair; named binding must not.
            'ship' => ApiMethodBuilder::make('ship', 'ship')->post()
                ->optionalParam('carrier', 'string', 'ups')
                ->requiredParam('boxes', 'integer')
                ->build(),
            'echo-types' => ApiMethodBuilder::make('echo-types', 'echoTypes')->post()
                ->requiredParam('note', 'string')
                ->requiredParam('count', 'integer')
                ->requiredParam('ratio', 'float')
                ->requiredParam('urgent', 'boolean')
                ->requiredParam('contact', 'email')
                ->requiredParam('ref', 'uuid')
                ->requiredParam('day', 'date')
                ->requiredParam('at', 'datetime')
                ->requiredParam('tags', 'array')
                ->requiredParam('blob', 'json')
                ->requiredParam('size', 'enum:a4,letter')
                ->optionalParam('doc', 'file')
                ->build(),
            'approve-later' => ApiMethodBuilder::make('approve-later', 'approveLater')->post()
                ->optionalParam('copies', 'integer')
                ->queued()
                ->build(),
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

    public function printLabels(int $copies = 1): array
    {
        return ['copies' => $copies, 'copies_type' => get_debug_type($copies)];
    }

    /**
     * @param  array<int, string>  $emails
     * @param  array<int, string>  $at
     * @param  array<int, float>  $weights
     */
    public function notifyPeople(array $emails, array $at = [], array $weights = []): array
    {
        return [
            'emails' => $emails,
            'at' => $at,
            'weight_types' => array_map(get_debug_type(...), $weights),
        ];
    }

    public function ship(int $boxes, string $carrier = 'ups'): array
    {
        return ['boxes' => $boxes, 'boxes_type' => get_debug_type($boxes), 'carrier' => $carrier];
    }

    /**
     * @param  array<int, string>  $tags
     */
    public function echoTypes(
        string $note,
        int $count,
        float $ratio,
        bool $urgent,
        string $contact,
        string $ref,
        string $day,
        string $at,
        array $tags,
        string $blob,
        string $size,
        ?UploadedFile $doc = null,
    ): array {
        return [
            'types' => [
                'note' => get_debug_type($note),
                'count' => get_debug_type($count),
                'ratio' => get_debug_type($ratio),
                'urgent' => get_debug_type($urgent),
                'contact' => get_debug_type($contact),
                'ref' => get_debug_type($ref),
                'day' => get_debug_type($day),
                'at' => get_debug_type($at),
                'tags' => get_debug_type($tags),
                'blob' => get_debug_type($blob),
                'size' => get_debug_type($size),
                'doc' => get_debug_type($doc),
            ],
            'count' => $count,
            'ratio' => $ratio,
            'urgent' => $urgent,
        ];
    }

    public function approveLater(int $copies = 1): array
    {
        $this->forceFill(['reference' => "approved-x{$copies}"])->save();

        return ['copies' => $copies];
    }
}
