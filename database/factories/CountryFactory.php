<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Factories;

use Blafast\Foundation\Models\Country;
use Blafast\Foundation\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Country>
     */
    protected $model = Country::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Synthetic UNIQUE codes: the old randomElement over a fixed pool of 8
        // real countries collided on the unique ISO columns as soon as a test
        // created a few rows — the suite's only order-flaky failures.
        return [
            'name' => 'Country '.fake()->unique()->numberBetween(1, 99999),
            'iso_alpha_2' => strtoupper(fake()->unique()->lexify('??')),
            'iso_alpha_3' => strtoupper(fake()->unique()->lexify('???')),
            'iso_numeric' => str_pad((string) fake()->unique()->numberBetween(1, 998), 3, '0', STR_PAD_LEFT),
            'phone_code' => '+'.fake()->numberBetween(1, 998),
            'currency_id' => Currency::factory(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the country is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a United States country.
     */
    public function unitedStates(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'United States',
            'iso_alpha_2' => 'US',
            'iso_alpha_3' => 'USA',
            'iso_numeric' => '840',
            'phone_code' => '+1',
        ]);
    }

    /**
     * Create a France country.
     */
    public function france(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'France',
            'iso_alpha_2' => 'FR',
            'iso_alpha_3' => 'FRA',
            'iso_numeric' => '250',
            'phone_code' => '+33',
        ]);
    }
}
