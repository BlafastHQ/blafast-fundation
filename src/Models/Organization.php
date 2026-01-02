<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use Blafast\Foundation\Database\Factories\OrganizationFactory;
use Blafast\Foundation\Traits\Addressable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Organization Model
 *
 * Represents a company or client entity in the BlaFast ERP system.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $address_id
 * @property string|null $vat_number
 * @property array|null $contact_details
 * @property array|null $settings
 * @property bool $is_active
 * @property string|null $peppol_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Blafast\Foundation\Models\Address|null $primaryAddress
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Blafast\Foundation\Models\Address> $addresses
 *
 * @method static \Blafast\Foundation\Database\Factories\OrganizationFactory factory($count = null, $state = [])
 * @method static Builder|Organization active()
 * @method static Builder|Organization bySlug(string $slug)
 *
 * @use HasFactory<OrganizationFactory>
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use Addressable;

    use HasFactory;
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'organizations';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'address_id',
        'vat_number',
        'contact_details',
        'settings',
        'is_active',
        'peppol_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'contact_details' => AsArrayObject::class,
        'settings' => AsArrayObject::class,
        'is_active' => 'boolean',
    ];

    /**
     * The "booting" method of the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate slug from name if not provided
        static::creating(function (Organization $organization) {
            if (empty($organization->slug)) {
                $organization->slug = Str::slug($organization->name);

                // Ensure uniqueness
                $count = 1;
                $originalSlug = $organization->slug;
                while (static::where('slug', $organization->slug)->exists()) {
                    $organization->slug = $originalSlug.'-'.$count;
                    $count++;
                }
            }
        });
    }

    /**
     * Get the primary address for this organization.
     *
     * @return BelongsTo<Address, Organization>
     */
    public function primaryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    /**
     * Get all users belonging to this organization.
     *
     * @return BelongsToMany<\App\Models\User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class)
            ->using(OrganizationUser::class)
            ->withPivot(['role', 'is_active', 'joined_at', 'left_at', 'metadata'])
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active organizations.
     *
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by slug.
     *
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * Get a setting value by key with optional default.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a setting value by key.
     */
    public function setSetting(string $key, mixed $value): self
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;

        return $this;
    }

    /**
     * Get a contact detail value by key with optional default.
     */
    public function getContactDetail(string $key, mixed $default = null): mixed
    {
        return data_get($this->contact_details, $key, $default);
    }

    /**
     * Set a contact detail value by key.
     */
    public function setContactDetail(string $key, mixed $value): self
    {
        $contactDetails = $this->contact_details ?? [];
        data_set($contactDetails, $key, $value);
        $this->contact_details = $contactDetails;

        return $this;
    }

    /**
     * Add a user to this organization.
     *
     * @param  \App\Models\User|object  $user
     * @param  array<string, mixed>  $metadata
     */
    public function addUser(object $user, string $role, array $metadata = []): void
    {
        $this->users()->attach($user->id, [
            'role' => $role,
            'is_active' => true,
            'joined_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Remove a user from this organization.
     *
     * @param  \App\Models\User|object  $user
     */
    public function removeUser(object $user): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'is_active' => false,
            'left_at' => now(),
        ]);
    }

    /**
     * Check if a user belongs to this organization.
     *
     * @param  \App\Models\User|object  $user
     */
    public function hasUser(object $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Get active users for this organization.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\User>
     */
    public function activeUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->users()->wherePivot('is_active', true)->get();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }
}
