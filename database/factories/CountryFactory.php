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
        $countries = [
            ['name' => 'United States', 'iso_alpha_2' => 'US', 'iso_alpha_3' => 'USA', 'iso_numeric' => '840', 'phone_code' => '+1'],
            ['name' => 'France', 'iso_alpha_2' => 'FR', 'iso_alpha_3' => 'FRA', 'iso_numeric' => '250', 'phone_code' => '+33'],
            ['name' => 'Germany', 'iso_alpha_2' => 'DE', 'iso_alpha_3' => 'DEU', 'iso_numeric' => '276', 'phone_code' => '+49'],
            ['name' => 'United Kingdom', 'iso_alpha_2' => 'GB', 'iso_alpha_3' => 'GBR', 'iso_numeric' => '826', 'phone_code' => '+44'],
            ['name' => 'Japan', 'iso_alpha_2' => 'JP', 'iso_alpha_3' => 'JPN', 'iso_numeric' => '392', 'phone_code' => '+81'],
            ['name' => 'Switzerland', 'iso_alpha_2' => 'CH', 'iso_alpha_3' => 'CHE', 'iso_numeric' => '756', 'phone_code' => '+41'],
            ['name' => 'Canada', 'iso_alpha_2' => 'CA', 'iso_alpha_3' => 'CAN', 'iso_numeric' => '124', 'phone_code' => '+1'],
            ['name' => 'Australia', 'iso_alpha_2' => 'AU', 'iso_alpha_3' => 'AUS', 'iso_numeric' => '036', 'phone_code' => '+61'],
        ];

        $country = fake()->randomElement($countries);

        return [
            'name' => $country['name'],
            'iso_alpha_2' => $country['iso_alpha_2'],
            'iso_alpha_3' => $country['iso_alpha_3'],
            'iso_numeric' => $country['iso_numeric'],
            'phone_code' => $country['phone_code'],
            'currency_id' => Currency::factory(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the country is inactive.
     *
     * @return static
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a United States country.
     *
     * @return static
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
     *
     * @return static
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
