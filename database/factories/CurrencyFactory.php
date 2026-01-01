<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Factories;

use Blafast\Foundation\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Currency>
     */
    protected $model = Currency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currencies = [
            ['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'decimal_places' => 2],
            ['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'decimal_places' => 2],
            ['name' => 'British Pound', 'code' => 'GBP', 'symbol' => '£', 'decimal_places' => 2],
            ['name' => 'Japanese Yen', 'code' => 'JPY', 'symbol' => '¥', 'decimal_places' => 0],
            ['name' => 'Swiss Franc', 'code' => 'CHF', 'symbol' => 'CHF', 'decimal_places' => 2],
            ['name' => 'Canadian Dollar', 'code' => 'CAD', 'symbol' => 'C$', 'decimal_places' => 2],
            ['name' => 'Australian Dollar', 'code' => 'AUD', 'symbol' => 'A$', 'decimal_places' => 2],
            ['name' => 'Chinese Yuan', 'code' => 'CNY', 'symbol' => '¥', 'decimal_places' => 2],
        ];

        $currency = fake()->randomElement($currencies);

        return [
            'name' => $currency['name'],
            'code' => $currency['code'],
            'symbol' => $currency['symbol'],
            'decimal_places' => $currency['decimal_places'],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the currency is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a USD currency.
     */
    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'decimal_places' => 2,
        ]);
    }

    /**
     * Create a EUR currency.
     */
    public function eur(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Euro',
            'code' => 'EUR',
            'symbol' => '€',
            'decimal_places' => 2,
        ]);
    }

    /**
     * Create a GBP currency.
     */
    public function gbp(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'British Pound',
            'code' => 'GBP',
            'symbol' => '£',
            'decimal_places' => 2,
        ]);
    }
}
