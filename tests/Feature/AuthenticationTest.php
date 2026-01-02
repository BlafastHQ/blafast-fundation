<?php

declare(strict_types=1);

use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create class alias for User model if it doesn't exist
    if (! class_exists(\App\Models\User::class)) {
        class_alias(User::class, \App\Models\User::class);
    }
});

test('user can login with valid credentials', function () {
    $user = createTestUser();
    $user->password = Hash::make('password');
    $user->save();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => [
                    'name',
                    'abilities',
                    'token',
                    'created_at',
                ],
                'relationships' => [
                    'user' => [
                        'data' => [
                            'type',
                            'id',
                        ],
                    ],
                ],
            ],
        ]);

    expect($response->json('data.type'))->toBe('auth-token')
        ->and($response->json('data.attributes.token'))->not->toBeNull();
});

test('user cannot login with invalid password', function () {
    $user = createTestUser();
    $user->password = Hash::make('password');
    $user->save();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'errors' => [[
                'status' => '401',
                'code' => 'INVALID_CREDENTIALS',
                'title' => 'Authentication Failed',
                'detail' => 'The provided credentials are incorrect.',
            ]],
        ]);
});

test('user cannot login with invalid email', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password',
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'errors' => [[
                'code' => 'INVALID_CREDENTIALS',
            ]],
        ]);
});

test('login requires email', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'password' => 'password',
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login requires password', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'device_name' => 'Test Device',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('login requires device name', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['device_name']);
});

test('authenticated user can logout', function () {
    $user = createTestUser();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'type' => 'auth-logout',
                'attributes' => [
                    'message' => 'Token successfully revoked.',
                ],
            ],
        ]);
});

test('unauthenticated user cannot logout', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(401);
});

test('authenticated user can logout from all devices', function () {
    $user = createTestUser();

    // Create multiple tokens
    $user->createToken('Device 1');
    $user->createToken('Device 2');
    $user->createToken('Device 3');

    Sanctum::actingAs($user);

    expect($user->tokens()->count())->toBe(3);

    $response = $this->postJson('/api/v1/auth/logout-all');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'type' => 'auth-logout',
                'attributes' => [
                    'message' => 'All tokens successfully revoked.',
                ],
            ],
        ]);

    expect($user->tokens()->count())->toBe(0);
});

test('authenticated user can get their profile', function () {
    $user = createTestUser();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'type' => 'user',
                'id' => $user->id,
                'attributes' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
});

test('unauthenticated user cannot get profile', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401);
});

test('authenticated user can list their tokens', function () {
    $user = createTestUser();

    $user->createToken('Device 1');
    $user->createToken('Device 2');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/auth/tokens');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('authenticated user can create new token', function () {
    $user = createTestUser();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/auth/tokens', [
        'name' => 'New Device',
        'abilities' => ['read'],
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'data' => [
                'type' => 'auth-token',
                'attributes' => [
                    'name' => 'New Device',
                    'abilities' => ['read'],
                ],
            ],
        ]);

    expect($response->json('data.attributes.token'))->not->toBeNull();
});

test('authenticated user can create token with default abilities', function () {
    $user = createTestUser();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/auth/tokens', [
        'name' => 'New Device',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'data' => [
                'attributes' => [
                    'abilities' => ['*'],
                ],
            ],
        ]);
});

test('authenticated user can revoke specific token', function () {
    $user = createTestUser();

    $token1 = $user->createToken('Device 1');
    $token2 = $user->createToken('Device 2');

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/v1/auth/tokens/'.$token1->accessToken->id);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'type' => 'auth-token-revoke',
            ],
        ]);

    expect($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()->id)->toBe($token2->accessToken->id);
});

test('user cannot revoke token belonging to another user', function () {
    $user1 = createTestUser();
    $user2 = createTestUser();

    $token1 = $user1->createToken('Device 1');

    Sanctum::actingAs($user2);

    $response = $this->deleteJson('/api/v1/auth/tokens/'.$token1->accessToken->id);

    $response->assertStatus(404)
        ->assertJson([
            'errors' => [[
                'code' => 'TOKEN_NOT_FOUND',
            ]],
        ]);
});

test('user cannot revoke non-existent token', function () {
    $user = createTestUser();
    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/v1/auth/tokens/non-existent-id');

    $response->assertStatus(404);
});

test('create token requires name', function () {
    $user = createTestUser();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/auth/tokens', [
        'abilities' => ['read'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});
