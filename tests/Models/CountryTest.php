<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Country;
use Blafast\Foundation\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('country can be created with factory', function () {
    $country = Country::factory()->create();

    expect($country)->toBeInstanceOf(Country::class)
        ->and($country->id)->not->toBeNull()
        ->and($country->iso_alpha_2)->not->toBeNull()
        ->and($country->iso_alpha_3)->not->toBeNull();
});

test('country belongs to currency', function () {
    $currency = Currency::factory()->create();
    $country = Country::factory()->create(['currency_id' => $currency->id]);

    expect($country->currency)->toBeInstanceOf(Currency::class)
        ->and($country->currency->id)->toBe($currency->id);
});

test('active scope returns only active countries', function () {
    Country::factory()->create(['is_active' => true]);
    Country::factory()->create(['is_active' => false]);

    $activeCountries = Country::active()->get();

    expect($activeCountries)->toHaveCount(1)
        ->and($activeCountries->first()->is_active)->toBeTrue();
});

test('byIsoAlpha2 scope filters by ISO alpha-2 code', function () {
    Country::factory()->create(['iso_alpha_2' => 'US']);
    Country::factory()->create(['iso_alpha_2' => 'FR']);

    $usCountry = Country::byIsoAlpha2('US')->first();

    expect($usCountry)->not->toBeNull()
        ->and($usCountry->iso_alpha_2)->toBe('US');
});

test('byIsoAlpha2 scope is case insensitive', function () {
    Country::factory()->create(['iso_alpha_2' => 'US']);

    $country = Country::byIsoAlpha2('us')->first();

    expect($country)->not->toBeNull()
        ->and($country->iso_alpha_2)->toBe('US');
});

test('byIsoAlpha3 scope filters by ISO alpha-3 code', function () {
    Country::factory()->create(['iso_alpha_3' => 'USA']);
    Country::factory()->create(['iso_alpha_3' => 'FRA']);

    $usCountry = Country::byIsoAlpha3('USA')->first();

    expect($usCountry)->not->toBeNull()
        ->and($usCountry->iso_alpha_3)->toBe('USA');
});

test('byIsoAlpha3 scope is case insensitive', function () {
    Country::factory()->create(['iso_alpha_3' => 'USA']);

    $country = Country::byIsoAlpha3('usa')->first();

    expect($country)->not->toBeNull()
        ->and($country->iso_alpha_3)->toBe('USA');
});
