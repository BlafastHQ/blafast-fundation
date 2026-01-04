<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Factories;

use Blafast\Foundation\Enums\DeferredRequestStatus;
use Blafast\Foundation\Models\DeferredApiRequest;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeferredApiRequest>
 */
class DeferredApiRequestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<DeferredApiRequest>
     */
    protected $model = DeferredApiRequest::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'http_method' => fake()->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'endpoint' => 'api/v1/'.fake()->word(),
            'payload' => fake()->boolean() ? ['data' => fake()->words(3)] : null,
            'query_params' => [],
            'headers' => [
                'accept' => 'application/vnd.api+json',
                'content-type' => 'application/vnd.api+json',
            ],
            'status' => DeferredRequestStatus::Pending,
            'progress' => null,
            'progress_message' => null,
            'result' => null,
            'result_status_code' => null,
            'error_code' => null,
            'error_message' => null,
            'attempts' => 0,
            'max_attempts' => 3,
            'priority' => 'default',
            'started_at' => null,
            'completed_at' => null,
            'expires_at' => now()->addHours(1),
        ];
    }

    /**
     * Indicate that the request is pending.
     */
    public function pending(): static
    {
        return $this->state([
            'status' => DeferredRequestStatus::Pending,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the request is processing.
     */
    public function processing(int $progress = 50): static
    {
        return $this->state([
            'status' => DeferredRequestStatus::Processing,
            'started_at' => now()->subMinutes(5),
            'attempts' => 1,
            'progress' => $progress,
            'progress_message' => 'Processing...',
        ]);
    }

    /**
     * Indicate that the request is completed.
     */
    public function completed(mixed $result = null): static
    {
        return $this->state([
            'status' => DeferredRequestStatus::Completed,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'result' => $result ?? ['data' => ['success' => true]],
            'result_status_code' => 200,
            'progress' => 100,
        ]);
    }

    /**
     * Indicate that the request failed.
     */
    public function failed(string $errorCode = 'EXECUTION_ERROR', string $errorMessage = 'Request failed'): static
    {
        return $this->state([
            'status' => DeferredRequestStatus::Failed,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'attempts' => 1,
        ]);
    }

    /**
     * Indicate that the request was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state([
            'status' => DeferredRequestStatus::Cancelled,
            'completed_at' => now(),
        ]);
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
     * Mark as expired.
     */
    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subHour()]);
    }
}
