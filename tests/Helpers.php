<?php

declare(strict_types=1);

/**
 * Helper function to create test users.
 */
function createTestUser(): \App\Models\User
{
    return \App\Models\User::create([
        'name' => fake()->name(),
        'email' => fake()->unique()->email(),
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
    ]);
}
