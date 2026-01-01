<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use Blafast\Foundation\Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Country Model
 *
 * Represents a country in the system (ISO 3166-1).
 *
 * @property string $id
 * @property string $name
 * @property string $iso_alpha_2
 * @property string $iso_alpha_3
 * @property string $iso_numeric
 * @property string $phone_code
 * @property string $currency_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Blafast\Foundation\Models\Currency $currency
 *
 * @method static \Blafast\Foundation\Database\Factories\CountryFactory factory($count = null, $state = [])
 * @method static Builder|Country active()
 * @method static Builder|Country byIsoAlpha2(string $code)
 * @method static Builder|Country byIsoAlpha3(string $code)
 *
 * @use HasFactory<CountryFactory>
 */
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'countries';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'iso_alpha_2',
        'iso_alpha_3',
        'iso_numeric',
        'phone_code',
        'currency_id',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the currency for this country.
     *
     * @return BelongsTo<Currency, Country>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Scope a query to only include active countries.
     *
     * @param Builder<Country> $query
     * @return Builder<Country>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by ISO Alpha-2 code.
     *
     * @param Builder<Country> $query
     * @param string $code
     * @return Builder<Country>
     */
    public function scopeByIsoAlpha2(Builder $query, string $code): Builder
    {
        return $query->where('iso_alpha_2', strtoupper($code));
    }

    /**
     * Scope a query to filter by ISO Alpha-3 code.
     *
     * @param Builder<Country> $query
     * @param string $code
     * @return Builder<Country>
     */
    public function scopeByIsoAlpha3(Builder $query, string $code): Builder
    {
        return $query->where('iso_alpha_3', strtoupper($code));
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return CountryFactory
     */
    protected static function newFactory(): CountryFactory
    {
        return CountryFactory::new();
    }
}
