<?php

declare(strict_types=1);

namespace Blafast\Foundation\Traits;

use Blafast\Foundation\Models\Address;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Addressable Trait
 *
 * Provides address management functionality to models.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Blafast\Foundation\Models\Address> $addresses
 */
trait Addressable
{
    /**
     * Get all addresses for this model.
     *
     * @return MorphMany<Address>
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * Get the primary address.
     *
     * @return Address|null
     */
    public function primaryAddress(): ?Address
    {
        return $this->addresses()->primary()->first();
    }

    /**
     * Get the billing address.
     *
     * @return Address|null
     */
    public function billingAddress(): ?Address
    {
        return $this->addresses()
            ->ofType(Address::TYPE_BILLING)
            ->primary()
            ->first();
    }

    /**
     * Get the shipping address.
     *
     * @return Address|null
     */
    public function shippingAddress(): ?Address
    {
        return $this->addresses()
            ->ofType(Address::TYPE_SHIPPING)
            ->primary()
            ->first();
    }

    /**
     * Get all addresses of a specific type.
     *
     * @param int $type
     * @return \Illuminate\Database\Eloquent\Collection<int, Address>
     */
    public function addressesOfType(int $type): \Illuminate\Database\Eloquent\Collection
    {
        return $this->addresses()->ofType($type)->get();
    }

    /**
     * Add a new address to this model.
     *
     * @param array<string, mixed> $attributes
     * @param bool $isPrimary
     * @return Address
     */
    public function addAddress(array $attributes, bool $isPrimary = false): Address
    {
        // If this should be primary, unset other primary addresses of the same type
        if ($isPrimary && isset($attributes['type'])) {
            $this->addresses()
                ->ofType($attributes['type'])
                ->primary()
                ->update(['is_primary' => false]);
        }

        return $this->addresses()->create(array_merge($attributes, [
            'is_primary' => $isPrimary,
        ]));
    }

    /**
     * Set an address as primary.
     *
     * @param Address $address
     * @return bool
     */
    public function setPrimaryAddress(Address $address): bool
    {
        // Unset other primary addresses of the same type
        $this->addresses()
            ->ofType($address->type)
            ->primary()
            ->where('id', '!=', $address->id)
            ->update(['is_primary' => false]);

        return $address->update(['is_primary' => true]);
    }

    /**
     * Check if the model has any addresses.
     *
     * @return bool
     */
    public function hasAddresses(): bool
    {
        return $this->addresses()->exists();
    }

    /**
     * Check if the model has a primary address.
     *
     * @return bool
     */
    public function hasPrimaryAddress(): bool
    {
        return $this->addresses()->primary()->exists();
    }

    /**
     * Check if the model has an address of a specific type.
     *
     * @param int $type
     * @return bool
     */
    public function hasAddressOfType(int $type): bool
    {
        return $this->addresses()->ofType($type)->exists();
    }
}
