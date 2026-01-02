<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\OrganizationUser;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create class alias for User model if it doesn't exist
    if (! class_exists(\App\Models\User::class)) {
        class_alias(User::class, \App\Models\User::class);
    }
});

test('organization user pivot has uuid primary key', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'admin');

    $pivot = OrganizationUser::first();

    expect($pivot->id)->not->toBeNull()
        ->and(Str::isUuid($pivot->id))->toBeTrue();
});

test('organization user pivot has correct relationships', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'admin');

    $pivot = OrganizationUser::first();

    expect($pivot->user)->not->toBeNull()
        ->and($pivot->organization)->not->toBeNull()
        ->and($pivot->organization->id)->toBe($organization->id);
});

test('active scope filters active memberships', function () {
    $organization = Organization::factory()->create();
    $activeUser = createTestUser();
    $inactiveUser = createTestUser();

    $organization->addUser($activeUser, 'member');
    $organization->addUser($inactiveUser, 'member');
    $organization->removeUser($inactiveUser);

    $activeMemberships = OrganizationUser::active()->get();

    expect($activeMemberships->count())->toBe(1)
        ->and($activeMemberships->first()->user_id)->toBe($activeUser->id);
});

test('by role scope filters by role', function () {
    $organization = Organization::factory()->create();
    $admin = createTestUser();
    $member = createTestUser();

    $organization->addUser($admin, 'admin');
    $organization->addUser($member, 'member');

    $adminMemberships = OrganizationUser::byRole('admin')->get();

    expect($adminMemberships->count())->toBe(1)
        ->and($adminMemberships->first()->role)->toBe('admin');
});

test('is active method checks both flags', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'member');
    $pivot = $organization->users()->first()->pivot;

    expect($pivot->isActive())->toBeTrue();

    $organization->removeUser($user);
    $pivot = $organization->users()->first()->pivot;

    expect($pivot->isActive())->toBeFalse();
});

test('can get metadata value', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'admin', [
        'permissions' => ['read', 'write'],
        'preferences' => [
            'theme' => 'dark',
        ],
    ]);

    $pivot = $organization->users()->first()->pivot;

    expect($pivot->getMetadata('permissions'))->toBe(['read', 'write'])
        ->and($pivot->getMetadata('preferences.theme'))->toBe('dark')
        ->and($pivot->getMetadata('nonexistent', 'default'))->toBe('default');
});

test('can set metadata value', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'member');

    $pivot = $organization->users()->first()->pivot;
    $pivot->setMetadata('custom_field', 'custom_value');
    $pivot->save();

    $pivot->refresh();

    expect($pivot->getMetadata('custom_field'))->toBe('custom_value');
});

test('can set nested metadata value', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'member');

    $pivot = $organization->users()->first()->pivot;
    $pivot->setMetadata('preferences.notifications.email', true);
    $pivot->save();

    $pivot->refresh();

    expect($pivot->getMetadata('preferences.notifications.email'))->toBe(true);
});

test('unique constraint prevents duplicate memberships', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'member');

    expect(fn () => $organization->addUser($user, 'admin'))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('joined at is set automatically', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'member');

    $pivot = $organization->users()->first()->pivot;

    expect($pivot->joined_at)->not->toBeNull()
        ->and($pivot->joined_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('left at is null for active memberships', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'member');

    $pivot = $organization->users()->first()->pivot;

    expect($pivot->left_at)->toBeNull();
});

test('left at is set when removing user', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'member');
    $organization->removeUser($user);

    $pivot = $organization->users()->first()->pivot;

    expect($pivot->left_at)->not->toBeNull()
        ->and($pivot->left_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('metadata is cast to array', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'member', ['key' => 'value']);

    $pivot = $organization->users()->first()->pivot;

    expect($pivot->metadata)->toBeArray()
        ->and($pivot->metadata['key'])->toBe('value');
});

test('can chain active and by role scopes', function () {
    $organization = Organization::factory()->create();
    $activeAdmin = createTestUser();
    $inactiveAdmin = createTestUser();
    $activeMember = createTestUser();

    $organization->addUser($activeAdmin, 'admin');
    $organization->addUser($inactiveAdmin, 'admin');
    $organization->addUser($activeMember, 'member');
    $organization->removeUser($inactiveAdmin);

    $activeAdmins = OrganizationUser::active()->byRole('admin')->get();

    expect($activeAdmins->count())->toBe(1)
        ->and($activeAdmins->first()->user_id)->toBe($activeAdmin->id);
});
