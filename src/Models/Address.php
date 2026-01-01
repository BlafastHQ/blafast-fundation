<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use Blafast\Foundation\Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Address Model
 *
 * Polymorphic model for storing addresses associated with various entities.
 *
 * @property string $id
 * @property string $addressable_type
 * @property string $addressable_id
 * @property int $type
 * @property string|null $label
 * @property string $line_1
 * @property string|null $line_2
 * @property string $city
 * @property string|null $state
 * @property string $postal_code
 * @property string $country_id
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $is_primary
 * @property bool $is_verified
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Model $addressable
 * @property-read \Blafast\Foundation\Models\Country $country
 * @property-read string $formatted_address
 * @property-read string $type_name
 *
 * @method static \Blafast\Foundation\Database\Factories\AddressFactory factory($count = null, $state = [])
 * @method static Builder|Address primary()
 * @method static Builder|Address ofType(int $type)
 * @method static Builder|Address verified()
 * @method static Builder|Address forAddressable(Model $addressable)
 *
 * @use HasFactory<AddressFactory>
 */
#[ObservedBy([\Blafast\Foundation\Observers\AddressObserver::class])]
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * Address type constants.
     */
    public const TYPE_BILLING = 1;
    public const TYPE_SHIPPING = 2;
    public const TYPE_HEADQUARTERS = 3;
    public const TYPE_BRANCH = 4;
    public const TYPE_WAREHOUSE = 5;
    public const TYPE_HOME = 6;
    public const TYPE_WORK = 7;

    /**
     * Address type labels.
     *
     * @var array<int, string>
     */
    public const TYPE_LABELS = [
        self::TYPE_BILLING => 'Billing',
        self::TYPE_SHIPPING => 'Shipping',
        self::TYPE_HEADQUARTERS => 'Headquarters',
        self::TYPE_BRANCH => 'Branch',
        self::TYPE_WAREHOUSE => 'Warehouse',
        self::TYPE_HOME => 'Home',
        self::TYPE_WORK => 'Work',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'addresses';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'addressable_type',
        'addressable_id',
        'type',
        'label',
        'line_1',
        'line_2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'latitude',
        'longitude',
        'is_primary',
        'is_verified',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the parent addressable model (user, organization, etc.).
     *
     * @return MorphTo<Model, Address>
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the country for this address.
     *
     * @return BelongsTo<Country, Address>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Scope a query to only include primary addresses.
     *
     * @param Builder<Address> $query
     * @return Builder<Address>
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope a query to filter by address type.
     *
     * @param Builder<Address> $query
     * @param int $type
     * @return Builder<Address>
     */
    public function scopeOfType(Builder $query, int $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include verified addresses.
     *
     * @param Builder<Address> $query
     * @return Builder<Address>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope a query to filter addresses for a specific addressable.
     *
     * @param Builder<Address> $query
     * @param Model $addressable
     * @return Builder<Address>
     */
    public function scopeForAddressable(Builder $query, Model $addressable): Builder
    {
        return $query->where('addressable_type', $addressable->getMorphClass())
            ->where('addressable_id', $addressable->getKey());
    }

    /**
     * Get the formatted address as a single string.
     *
     * @return Attribute<string, never>
     */
    protected function formattedAddress(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $parts = array_filter([
                    $this->line_1,
                    $this->line_2,
                    $this->city,
                    $this->state,
                    $this->postal_code,
                    $this->country?->name,
                ]);

                return implode(', ', $parts);
            }
        );
    }

    /**
     * Get the type name as a readable string.
     *
     * @return Attribute<string, never>
     */
    protected function typeName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => self::TYPE_LABELS[$this->type] ?? 'Unknown'
        );
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return AddressFactory
     */
    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }
}
