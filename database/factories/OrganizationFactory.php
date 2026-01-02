<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Factories;

use Blafast\Foundation\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Organization>
     */
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyName = fake()->company();

        return [
            'name' => $companyName,
            'slug' => Str::slug($companyName).'-'.fake()->unique()->numberBetween(1000, 9999),
            'vat_number' => fake()->optional()->regexify('[A-Z]{2}[0-9]{9}'),
            'contact_details' => [
                'email' => fake()->companyEmail(),
                'phone' => fake()->phoneNumber(),
                'website' => fake()->optional()->url(),
                'linkedin' => fake()->optional()->url(),
                'twitter' => fake()->optional()->userName(),
            ],
            'settings' => [
                'date_format' => fake()->randomElement(['Y-m-d', 'd/m/Y', 'm/d/Y']),
                'timezone' => fake()->timezone(),
                'currency' => fake()->currencyCode(),
                'language' => fake()->languageCode(),
            ],
            'is_active' => true,
            'peppol_id' => fake()->optional()->regexify('[0-9]{4}:[A-Z0-9]{10}'),
        ];
    }

    /**
     * Indicate that the organization is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific slug.
     */
    public function withSlug(string $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => $slug,
        ]);
    }

    /**
     * Add VAT number.
     */
    public function withVat(?string $vatNumber = null): static
    {
        return $this->state(fn (array $attributes) => [
            'vat_number' => $vatNumber ?? fake()->regexify('[A-Z]{2}[0-9]{9}'),
        ]);
    }

    /**
     * Add PEPPOL ID.
     */
    public function withPeppol(?string $peppolId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'peppol_id' => $peppolId ?? fake()->regexify('[0-9]{4}:[A-Z0-9]{10}'),
        ]);
    }

    /**
     * Set custom contact details.
     *
     * @param  array<string, mixed>  $contactDetails
     */
    public function withContactDetails(array $contactDetails): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_details' => array_merge($attributes['contact_details'] ?? [], $contactDetails),
        ]);
    }

    /**
     * Set custom settings.
     *
     * @param  array<string, mixed>  $settings
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], $settings),
        ]);
    }

    /**
     * Create a small business organization.
     */
    public function smallBusiness(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->company().' Ltd',
            'settings' => [
                'date_format' => 'Y-m-d',
                'timezone' => 'UTC',
                'currency' => 'USD',
                'language' => 'en',
                'employees_count' => fake()->numberBetween(1, 50),
            ],
        ]);
    }

    /**
     * Create an enterprise organization.
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->company().' Corporation',
            'vat_number' => fake()->regexify('[A-Z]{2}[0-9]{9}'),
            'peppol_id' => fake()->regexify('[0-9]{4}:[A-Z0-9]{10}'),
            'settings' => [
                'date_format' => 'Y-m-d',
                'timezone' => 'UTC',
                'currency' => 'USD',
                'language' => 'en',
                'employees_count' => fake()->numberBetween(500, 10000),
                'multi_currency' => true,
                'advanced_reporting' => true,
            ],
        ]);
    }
}
