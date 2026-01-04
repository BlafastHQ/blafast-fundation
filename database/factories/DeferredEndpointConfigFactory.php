<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Factories;

use Blafast\Foundation\Models\DeferredEndpointConfig;
use Blafast\Foundation\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeferredEndpointConfig>
 */
class DeferredEndpointConfigFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<DeferredEndpointConfig>
     */
    protected $model = DeferredEndpointConfig::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'organization_id' => null, // Global by default
            'http_method' => fake()->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'endpoint_pattern' => 'api/v1/' . fake()->word() . '/*',
            'is_active' => true,
            'force_deferred' => false,
            'priority' => 'default',
            'timeout' => 300,
            'result_ttl' => 3600,
        ];
    }

    /**
     * Indicate that the config is for a specific organization.
     */
    public function forOrganization(Organization $organization = null): static
    {
        return $this->state([
            'organization_id' => $organization ? $organization->id : Organization::factory(),
        ]);
    }

    /**
     * Indicate that the config is inactive.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Indicate that deferral is forced.
     */
    public function forceDeferred(): static
    {
        return $this->state(['force_deferred' => true]);
    }

    /**
     * Set high priority.
     */
    public function highPriority(): static
    {
        return $this->state(['priority' => 'high']);
    }

    /**
     * Set low priority.
     */
    public function lowPriority(): static
    {
        return $this->state(['priority' => 'low']);
    }

    /**
     * Set custom timeout.
     */
    public function withTimeout(int $seconds): static
    {
        return $this->state(['timeout' => $seconds]);
    }

    /**
     * Set custom result TTL.
     */
    public function withTtl(int $seconds): static
    {
        return $this->state(['result_ttl' => $seconds]);
    }
}
