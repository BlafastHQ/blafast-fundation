<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Country;
use Blafast\Foundation\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('currency can be created with factory', function () {
    $currency = Currency::factory()->create();

    expect($currency)->toBeInstanceOf(Currency::class)
        ->and($currency->id)->not->toBeNull()
        ->and($currency->code)->not->toBeNull()
        ->and($currency->symbol)->not->toBeNull();
});

test('currency has countries relationship', function () {
    $currency = Currency::factory()->create();
    $country = Country::factory()->create(['currency_id' => $currency->id]);

    expect($currency->countries)->toHaveCount(1)
        ->and($currency->countries->first()->id)->toBe($country->id);
});

test('active scope returns only active currencies', function () {
    Currency::factory()->create(['is_active' => true]);
    Currency::factory()->create(['is_active' => false]);

    $activeCurrencies = Currency::active()->get();

    expect($activeCurrencies)->toHaveCount(1)
        ->and($activeCurrencies->first()->is_active)->toBeTrue();
});

test('byCode scope filters by currency code', function () {
    Currency::factory()->create(['code' => 'USD']);
    Currency::factory()->create(['code' => 'EUR']);

    $usdCurrency = Currency::byCode('USD')->first();

    expect($usdCurrency)->not->toBeNull()
        ->and($usdCurrency->code)->toBe('USD');
});

test('byCode scope is case insensitive', function () {
    Currency::factory()->create(['code' => 'USD']);

    $currency = Currency::byCode('usd')->first();

    expect($currency)->not->toBeNull()
        ->and($currency->code)->toBe('USD');
});
