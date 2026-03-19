<?php

declare(strict_types=1);
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Helper function to create test users.
 */
function createTestUser(): User
{
    return User::create([
        'name' => fake()->name(),
        'email' => fake()->unique()->email(),
        'password' => Hash::make('password'),
    ]);
}
