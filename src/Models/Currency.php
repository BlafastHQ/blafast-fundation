<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use Blafast\Foundation\Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Currency Model
 *
 * Represents a currency in the system (ISO 4217).
 *
 * @property string $id
 * @property string $name
 * @property string $code
 * @property string $symbol
 * @property int $decimal_places
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Blafast\Foundation\Models\Country> $countries
 *
 * @method static \Blafast\Foundation\Database\Factories\CurrencyFactory factory($count = null, $state = [])
 * @method static Builder|Currency active()
 * @method static Builder|Currency byCode(string $code)
 *
 * @use HasFactory<CurrencyFactory>
 */
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'currencies';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'symbol',
        'decimal_places',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
    ];

    /**
     * Get the countries that use this currency.
     *
     * @return HasMany<Country>
     */
    public function countries(): HasMany
    {
        return $this->hasMany(Country::class);
    }

    /**
     * Scope a query to only include active currencies.
     *
     * @param  Builder<Currency>  $query
     * @return Builder<Currency>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by currency code.
     *
     * @param  Builder<Currency>  $query
     * @return Builder<Currency>
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', strtoupper($code));
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): CurrencyFactory
    {
        return CurrencyFactory::new();
    }
}
