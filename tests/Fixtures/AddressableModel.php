<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Fixtures;

use Blafast\Foundation\Traits\Addressable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Test model for Addressable trait testing.
 *
 * @property string $id
 * @property string $name
 */
class AddressableModel extends Model
{
    use Addressable;
    use HasFactory;
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'addressable_models';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Blafast\Foundation\Tests\Fixtures\Factories\AddressableModelFactory
     */
    protected static function newFactory(): Factories\AddressableModelFactory
    {
        return Factories\AddressableModelFactory::new();
    }
}
