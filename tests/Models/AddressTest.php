<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Address;
use Blafast\Foundation\Models\Country;
use Blafast\Foundation\Models\Currency;
use Blafast\Foundation\Tests\Fixtures\AddressableModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a currency and country for testing
    $this->currency = Currency::factory()->create(['code' => 'USD']);
    $this->country = Country::factory()->create(['currency_id' => $this->currency->id]);
});

test('address can be created with factory', function () {
    $address = Address::factory()->create(['country_id' => $this->country->id]);

    expect($address)->toBeInstanceOf(Address::class)
        ->and($address->id)->not->toBeNull()
        ->and($address->line_1)->not->toBeNull()
        ->and($address->city)->not->toBeNull()
        ->and($address->postal_code)->not->toBeNull();
});

test('address belongs to country', function () {
    $address = Address::factory()->create(['country_id' => $this->country->id]);

    expect($address->country)->toBeInstanceOf(Country::class)
        ->and($address->country->id)->toBe($this->country->id);
});

test('address has polymorphic relationship to addressable', function () {
    $addressable = AddressableModel::factory()->create();
    $address = Address::factory()->create([
        'addressable_type' => $addressable->getMorphClass(),
        'addressable_id' => $addressable->id,
        'country_id' => $this->country->id,
    ]);

    expect($address->addressable)->toBeInstanceOf(AddressableModel::class)
        ->and($address->addressable->id)->toBe($addressable->id);
});

test('primary scope returns only primary addresses', function () {
    Address::factory()->create(['is_primary' => true, 'country_id' => $this->country->id]);
    Address::factory()->create(['is_primary' => false, 'country_id' => $this->country->id]);

    $primaryAddresses = Address::primary()->get();

    expect($primaryAddresses)->toHaveCount(1)
        ->and($primaryAddresses->first()->is_primary)->toBeTrue();
});

test('ofType scope filters by address type', function () {
    Address::factory()->billing()->create(['country_id' => $this->country->id]);
    Address::factory()->shipping()->create(['country_id' => $this->country->id]);

    $billingAddresses = Address::ofType(Address::TYPE_BILLING)->get();

    expect($billingAddresses)->toHaveCount(1)
        ->and($billingAddresses->first()->type)->toBe(Address::TYPE_BILLING);
});

test('verified scope returns only verified addresses', function () {
    Address::factory()->verified()->create(['country_id' => $this->country->id]);
    Address::factory()->create(['is_verified' => false, 'country_id' => $this->country->id]);

    $verifiedAddresses = Address::verified()->get();

    expect($verifiedAddresses)->toHaveCount(1)
        ->and($verifiedAddresses->first()->is_verified)->toBeTrue();
});

test('forAddressable scope filters by addressable', function () {
    $addressable1 = AddressableModel::factory()->create();
    $addressable2 = AddressableModel::factory()->create();

    Address::factory()->create([
        'addressable_type' => $addressable1->getMorphClass(),
        'addressable_id' => $addressable1->id,
        'country_id' => $this->country->id,
    ]);
    Address::factory()->create([
        'addressable_type' => $addressable2->getMorphClass(),
        'addressable_id' => $addressable2->id,
        'country_id' => $this->country->id,
    ]);

    $addresses = Address::forAddressable($addressable1)->get();

    expect($addresses)->toHaveCount(1)
        ->and($addresses->first()->addressable_id)->toBe($addressable1->id);
});

test('formatted address accessor returns complete address', function () {
    $address = Address::factory()->create([
        'line_1' => '123 Main St',
        'line_2' => 'Apt 4B',
        'city' => 'New York',
        'state' => 'NY',
        'postal_code' => '10001',
        'country_id' => $this->country->id,
    ]);

    expect($address->formatted_address)
        ->toContain('123 Main St')
        ->toContain('New York')
        ->toContain('10001');
});

test('type name accessor returns readable type', function () {
    $address = Address::factory()->billing()->create(['country_id' => $this->country->id]);

    expect($address->type_name)->toBe('Billing');
});

test('observer unsets other primary addresses when creating new primary', function () {
    $addressable = AddressableModel::factory()->create();

    $address1 = Address::factory()->billing()->primary()->create([
        'addressable_type' => $addressable->getMorphClass(),
        'addressable_id' => $addressable->id,
        'country_id' => $this->country->id,
    ]);

    expect($address1->is_primary)->toBeTrue();

    $address2 = Address::factory()->billing()->primary()->create([
        'addressable_type' => $addressable->getMorphClass(),
        'addressable_id' => $addressable->id,
        'country_id' => $this->country->id,
    ]);

    $address1->refresh();

    expect($address2->is_primary)->toBeTrue()
        ->and($address1->is_primary)->toBeFalse();
});

test('observer handles different address types independently', function () {
    $addressable = AddressableModel::factory()->create();

    $billingAddress = Address::factory()->billing()->primary()->create([
        'addressable_type' => $addressable->getMorphClass(),
        'addressable_id' => $addressable->id,
        'country_id' => $this->country->id,
    ]);

    $shippingAddress = Address::factory()->shipping()->primary()->create([
        'addressable_type' => $addressable->getMorphClass(),
        'addressable_id' => $addressable->id,
        'country_id' => $this->country->id,
    ]);

    expect($billingAddress->is_primary)->toBeTrue()
        ->and($shippingAddress->is_primary)->toBeTrue();
});

test('observer sets next address as primary when deleting primary address', function () {
    $addressable = AddressableModel::factory()->create();

    $address1 = Address::factory()->billing()->primary()->create([
        'addressable_type' => $addressable->getMorphClass(),
        'addressable_id' => $addressable->id,
        'country_id' => $this->country->id,
    ]);

    $address2 = Address::factory()->billing()->create([
        'addressable_type' => $addressable->getMorphClass(),
        'addressable_id' => $addressable->id,
        'country_id' => $this->country->id,
    ]);

    $address1->delete();
    $address2->refresh();

    expect($address2->is_primary)->toBeTrue();
});
