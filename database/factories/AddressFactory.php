<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Factories;

use Blafast\Foundation\Models\Address;
use Blafast\Foundation\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Address>
     */
    protected $model = Address::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement([
                Address::TYPE_BILLING,
                Address::TYPE_SHIPPING,
                Address::TYPE_HOME,
                Address::TYPE_WORK,
            ]),
            'label' => fake()->optional()->words(2, true),
            'line_1' => fake()->streetAddress(),
            'line_2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->city(),
            'state' => fake()->optional()->state(),
            'postal_code' => fake()->postcode(),
            'country_id' => Country::factory(),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
            'is_primary' => false,
            'is_verified' => fake()->boolean(30),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the address is primary.
     *
     * @return static
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Indicate that the address is verified.
     *
     * @return static
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
        ]);
    }

    /**
     * Create a billing address.
     *
     * @return static
     */
    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Address::TYPE_BILLING,
            'label' => 'Billing Address',
        ]);
    }

    /**
     * Create a shipping address.
     *
     * @return static
     */
    public function shipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Address::TYPE_SHIPPING,
            'label' => 'Shipping Address',
        ]);
    }

    /**
     * Create a headquarters address.
     *
     * @return static
     */
    public function headquarters(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Address::TYPE_HEADQUARTERS,
            'label' => 'Headquarters',
        ]);
    }

    /**
     * Create a home address.
     *
     * @return static
     */
    public function home(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Address::TYPE_HOME,
            'label' => 'Home',
        ]);
    }

    /**
     * Create a work address.
     *
     * @return static
     */
    public function work(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Address::TYPE_WORK,
            'label' => 'Work',
        ]);
    }

    /**
     * Create a US address.
     *
     * @return static
     */
    public function unitedStates(): static
    {
        return $this->state(function (array $attributes) {
            $usCountry = Country::firstOrCreate(
                ['iso_alpha_2' => 'US'],
                [
                    'name' => 'United States',
                    'iso_alpha_3' => 'USA',
                    'iso_numeric' => '840',
                    'phone_code' => '+1',
                    'currency_id' => \Blafast\Foundation\Models\Currency::factory(),
                    'is_active' => true,
                ]
            );

            return [
                'line_1' => fake()->streetAddress(),
                'line_2' => fake()->optional()->secondaryAddress(),
                'city' => fake()->city(),
                'state' => fake()->stateAbbr(),
                'postal_code' => fake()->postcode(),
                'country_id' => $usCountry->id,
            ];
        });
    }

    /**
     * Create a French address.
     *
     * @return static
     */
    public function france(): static
    {
        return $this->state(function (array $attributes) {
            $frCountry = Country::firstOrCreate(
                ['iso_alpha_2' => 'FR'],
                [
                    'name' => 'France',
                    'iso_alpha_3' => 'FRA',
                    'iso_numeric' => '250',
                    'phone_code' => '+33',
                    'currency_id' => \Blafast\Foundation\Models\Currency::factory(),
                    'is_active' => true,
                ]
            );

            return [
                'line_1' => fake()->streetAddress(),
                'line_2' => fake()->optional()->secondaryAddress(),
                'city' => fake()->city(),
                'state' => null,
                'postal_code' => fake()->postcode(),
                'country_id' => $frCountry->id,
            ];
        });
    }

    /**
     * Set GPS coordinates.
     *
     * @return static
     */
    public function withCoordinates(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ]);
    }

    /**
     * Add metadata.
     *
     * @param array<string, mixed> $metadata
     * @return static
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }
}
