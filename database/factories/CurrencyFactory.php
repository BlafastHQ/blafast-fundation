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
        // Synthetic UNIQUE codes: the old randomElement over a fixed pool of 8
        // real currencies collided on the unique code column (order-flaky).
        return [
            'name' => 'Currency '.fake()->unique()->numberBetween(1, 99999),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'symbol' => '¤',
            'decimal_places' => 2,
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
