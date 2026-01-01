<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Fixtures\Factories;

use Blafast\Foundation\Tests\Fixtures\AddressableModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddressableModel>
 */
class AddressableModelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AddressableModel>
     */
    protected $model = AddressableModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
        ];
    }
}
