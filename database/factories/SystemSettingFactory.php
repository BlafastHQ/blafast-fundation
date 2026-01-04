<?php

declare(strict_types=1);

namespace Blafast\Foundation\Database\Factories;

use Blafast\Foundation\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SystemSetting>
     */
    protected $model = SystemSetting::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->word(),
            'value' => fake()->word(),
            'type' => 'string',
            'description' => fake()->sentence(),
            'is_public' => false,
        ];
    }

    /**
     * Indicate that the setting is public.
     */
    public function public(): static
    {
        return $this->state(['is_public' => true]);
    }

    /**
     * Create a boolean setting.
     */
    public function boolean(bool $value = true): static
    {
        return $this->state([
            'value' => $value ? '1' : '0',
            'type' => 'boolean',
        ]);
    }

    /**
     * Create an integer setting.
     */
    public function integer(int $value = 100): static
    {
        return $this->state([
            'value' => (string) $value,
            'type' => 'integer',
        ]);
    }

    /**
     * Create a JSON setting.
     */
    public function json(array $value = []): static
    {
        return $this->state([
            'value' => json_encode($value),
            'type' => 'json',
        ]);
    }
}
