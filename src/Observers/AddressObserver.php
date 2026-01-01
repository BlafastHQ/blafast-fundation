<?php

declare(strict_types=1);

namespace Blafast\Foundation\Observers;

use Blafast\Foundation\Models\Address;

/**
 * Address Observer
 *
 * Handles automatic primary address management.
 */
class AddressObserver
{
    /**
     * Handle the Address "creating" event.
     *
     * When creating a new primary address, unset other primary addresses
     * of the same type for the same addressable entity.
     *
     * @param Address $address
     * @return void
     */
    public function creating(Address $address): void
    {
        if ($address->is_primary) {
            $this->unsetOtherPrimaryAddresses($address);
        }
    }

    /**
     * Handle the Address "updating" event.
     *
     * When setting an address as primary, unset other primary addresses
     * of the same type for the same addressable entity.
     *
     * @param Address $address
     * @return void
     */
    public function updating(Address $address): void
    {
        // Only handle if is_primary is being changed to true
        if ($address->isDirty('is_primary') && $address->is_primary) {
            $this->unsetOtherPrimaryAddresses($address);
        }
    }

    /**
     * Handle the Address "deleted" event.
     *
     * If the deleted address was primary, set the first remaining address
     * of the same type as primary.
     *
     * @param Address $address
     * @return void
     */
    public function deleted(Address $address): void
    {
        if ($address->is_primary) {
            // Find the first remaining address of the same type
            $nextPrimary = Address::query()
                ->where('addressable_type', $address->addressable_type)
                ->where('addressable_id', $address->addressable_id)
                ->where('type', $address->type)
                ->where('id', '!=', $address->id)
                ->first();

            if ($nextPrimary) {
                $nextPrimary->is_primary = true;
                $nextPrimary->saveQuietly(); // Save without triggering events
            }
        }
    }

    /**
     * Unset other primary addresses of the same type for the same addressable.
     *
     * @param Address $address
     * @return void
     */
    private function unsetOtherPrimaryAddresses(Address $address): void
    {
        Address::query()
            ->where('addressable_type', $address->addressable_type)
            ->where('addressable_id', $address->addressable_id)
            ->where('type', $address->type)
            ->where('is_primary', true)
            ->when($address->exists, function ($query) use ($address) {
                $query->where('id', '!=', $address->id);
            })
            ->update(['is_primary' => false]);
    }
}
