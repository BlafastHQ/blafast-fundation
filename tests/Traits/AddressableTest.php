<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Address;
use Blafast\Foundation\Models\Country;
use Blafast\Foundation\Models\Currency;
use Blafast\Foundation\Tests\Fixtures\AddressableModel;

beforeEach(function () {
    // Create a currency and country for testing
    $this->currency = Currency::factory()->create(['code' => 'USD']);
    $this->country = Country::factory()->create(['currency_id' => $this->currency->id]);
});

test('addressable model has addresses relationship', function () {
    $addressable = AddressableModel::factory()->create();

    expect($addressable->addresses())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
});

test('can add address to addressable model', function () {
    $addressable = AddressableModel::factory()->create();

    $address = $addressable->addAddress([
        'type' => Address::TYPE_BILLING,
        'line_1' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'postal_code' => '10001',
        'country_id' => $this->country->id,
    ]);

    expect($address)->toBeInstanceOf(Address::class)
        ->and($address->addressable_id)->toBe($addressable->id)
        ->and($addressable->addresses)->toHaveCount(1);
});

test('can add primary address', function () {
    $addressable = AddressableModel::factory()->create();

    $address = $addressable->addAddress([
        'type' => Address::TYPE_BILLING,
        'line_1' => '123 Main St',
        'city' => 'New York',
        'postal_code' => '10001',
        'country_id' => $this->country->id,
    ], isPrimary: true);

    expect($address->is_primary)->toBeTrue();
});

test('primaryAddress returns the primary address', function () {
    $addressable = AddressableModel::factory()->create();

    $address = $addressable->addAddress([
        'type' => Address::TYPE_BILLING,
        'line_1' => '123 Main St',
        'city' => 'New York',
        'postal_code' => '10001',
        'country_id' => $this->country->id,
    ], isPrimary: true);

    expect($addressable->primaryAddress())->not->toBeNull()
        ->and($addressable->primaryAddress()->id)->toBe($address->id);
});

test('billingAddress returns the primary billing address', function () {
    $addressable = AddressableModel::factory()->create();

    $billingAddress = $addressable->addAddress([
        'type' => Address::TYPE_BILLING,
        'line_1' => '123 Main St',
        'city' => 'New York',
        'postal_code' => '10001',
        'country_id' => $this->country->id,
    ], isPrimary: true);

    expect($addressable->billingAddress())->not->toBeNull()
        ->and($addressable->billingAddress()->id)->toBe($billingAddress->id);
});

test('shippingAddress returns the primary shipping address', function () {
    $addressable = AddressableModel::factory()->create();

    $shippingAddress = $addressable->addAddress([
        'type' => Address::TYPE_SHIPPING,
        'line_1' => '456 Oak Ave',
        'city' => 'Boston',
        'postal_code' => '02101',
        'country_id' => $this->country->id,
    ], isPrimary: true);

    expect($addressable->shippingAddress())->not->toBeNull()
        ->and($addressable->shippingAddress()->id)->toBe($shippingAddress->id);
});

test('addressesOfType returns all addresses of a specific type', function () {
    $addressable = AddressableModel::factory()->create();

    $addressable->addAddress([
        'type' => Address::TYPE_BILLING,
        'line_1' => '123 Main St',
        'city' => 'New York',
        'postal_code' => '10001',
        'country_id' => $this->country->id,
    ]);

    $addressable->addAddress([
        'type' => Address::TYPE_BILLING,
        'line_1' => '456 Oak Ave',
        'city' => 'Boston',
        'postal_code' => '02101',
        'country_id' => $this->country->id,
    ]);

    $addressable->addAddress([
        'type' => Address::TYPE_SHIPPING,
        'line_1' => '789 Pine Rd',
        'city' => 'Chicago',
        'postal_code' => '60601',
        'country_id' => $this->country->id,
    ]);

    $billingAddresses = $addressable->addressesOfType(Address::TYPE_BILLING);

    expect($billingAddresses)->toHaveCount(2);
});

test('setPrimaryAddress unsets other primary addresses of same type', function () {
    $addressable = AddressableModel::factory()->create();

    $address1 = $addressable->addAddress([
        'type' => Address::TYPE_BILLING,
        'line_1' => '123 Main St',
        'city' => 'New York',
        'postal_code' => '10001',
        'country_id' => $this->country->id,
    ], isPrimary: true);

    $address2 = $addressable->addAddress([
        'type' => Address::TYPE_BILLING,
        'line_1' => '456 Oak Ave',
        'city' => 'Boston',
        'postal_code' => '02101',
        'country_id' => $this->country->id,
    ]);

    $addressable->setPrimaryAddress($address2);

    $address1->refresh();
    $address2->refresh();

    expect($address1->is_primary)->toBeFalse()
        ->and($address2->is_primary)->toBeTrue();
});

test('hasAddresses returns true when model has addresses', function () {
    $addressable = AddressableModel::factory()->create();

    expect($addressable->hasAddresses())->toBeFalse();

    $addressable->addAddress([
        'type' => Address::TYPE_BILLING,
        'line_1' => '123 Main St',
        'city' => 'New York',
        'postal_code' => '10001',
        'country_id' => $this->country->id,
    ]);

    expect($addressable->hasAddresses())->toBeTrue();
});

test('hasPrimaryAddress returns true when model has primary address', function () {
    $addressable = AddressableModel::factory()->create();

    expect($addressable->hasPrimaryAddress())->toBeFalse();

    $addressable->addAddress([
        'type' => Address::TYPE_BILLING,
        'line_1' => '123 Main St',
        'city' => 'New York',
        'postal_code' => '10001',
        'country_id' => $this->country->id,
    ], isPrimary: true);

    expect($addressable->hasPrimaryAddress())->toBeTrue();
});

test('hasAddressOfType returns true when model has address of specific type', function () {
    $addressable = AddressableModel::factory()->create();

    expect($addressable->hasAddressOfType(Address::TYPE_BILLING))->toBeFalse();

    $addressable->addAddress([
        'type' => Address::TYPE_BILLING,
        'line_1' => '123 Main St',
        'city' => 'New York',
        'postal_code' => '10001',
        'country_id' => $this->country->id,
    ]);

    expect($addressable->hasAddressOfType(Address::TYPE_BILLING))->toBeTrue()
        ->and($addressable->hasAddressOfType(Address::TYPE_SHIPPING))->toBeFalse();
});
