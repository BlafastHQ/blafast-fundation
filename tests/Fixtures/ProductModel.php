<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Fixtures;

use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Traits\ExposesApiStructure;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Test fixture model with media support for testing file endpoints.
 */
class ProductModel extends Model implements HasApiStructure, HasMedia
{
    use ExposesApiStructure;
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;

    protected $table = 'test_products';

    protected $fillable = [
        'name',
        'description',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public');

        $this->addMediaCollection('documents')
            ->useDisk('public');

        $this->addMediaCollection('private_files')
            ->useDisk('private');
    }

    /**
     * Define API structure.
     */
    public static function apiStructure(): array
    {
        return [
            'slug' => 'product',
            'label' => 'Product',
            'fields' => [
                ['name' => 'id', 'label' => 'ID', 'type' => 'uuid', 'filterable' => true, 'sortable' => false],
                ['name' => 'name', 'label' => 'Name', 'type' => 'string', 'filterable' => true, 'sortable' => true, 'searchable' => true],
                ['name' => 'description', 'label' => 'Description', 'type' => 'text', 'filterable' => false, 'sortable' => false, 'searchable' => true],
                ['name' => 'price', 'label' => 'Price', 'type' => 'decimal', 'filterable' => true, 'sortable' => true],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'boolean', 'filterable' => true, 'sortable' => true],
                ['name' => 'created_at', 'label' => 'Created', 'type' => 'datetime', 'filterable' => true, 'sortable' => true],
                ['name' => 'updated_at', 'label' => 'Updated', 'type' => 'datetime', 'filterable' => true, 'sortable' => true],
            ],
            'media_collections' => [
                'images' => [
                    'label' => 'Product Images',
                    'multiple' => true,
                    'disk' => 'public',
                ],
                'documents' => [
                    'label' => 'Product Documents',
                    'multiple' => true,
                    'disk' => 'public',
                ],
                'private_files' => [
                    'label' => 'Private Files',
                    'multiple' => true,
                    'disk' => 'private',
                ],
            ],
            'allowed_includes' => [],
            'pagination' => [
                'default_size' => 15,
                'max_size' => 100,
            ],
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ProductModelFactory::new();
    }
}
