<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Address;
use Blafast\Foundation\Models\Country;
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

test('can create organization', function () {
    $organization = Organization::factory()->create([
        'name' => 'Test Organization',
    ]);

    expect($organization)->toBeInstanceOf(Organization::class)
        ->and($organization->name)->toBe('Test Organization')
        ->and($organization->id)->not->toBeNull()
        ->and($organization->slug)->not->toBeNull();
});

test('can create organization using factory', function () {
    $organization = Organization::factory()->create();

    expect($organization)->toBeInstanceOf(Organization::class)
        ->and($organization->id)->not->toBeNull()
        ->and($organization->name)->not->toBeNull()
        ->and($organization->slug)->not->toBeNull();
});

test('auto generates slug from name', function () {
    $organization = Organization::create([
        'name' => 'My Test Company',
    ]);

    expect($organization->slug)->toBe('my-test-company');
});

test('ensures slug uniqueness', function () {
    Organization::create(['name' => 'Test Company']);
    $second = Organization::create(['name' => 'Test Company']);
    $third = Organization::create(['name' => 'Test Company']);

    expect($second->slug)->toBe('test-company-1')
        ->and($third->slug)->toBe('test-company-2');
});

test('can set custom slug', function () {
    $organization = Organization::create([
        'name' => 'Test Company',
        'slug' => 'custom-slug',
    ]);

    expect($organization->slug)->toBe('custom-slug');
});

test('has active scope', function () {
    Organization::factory()->count(3)->create(['is_active' => true]);
    Organization::factory()->count(2)->inactive()->create();

    expect(Organization::active()->count())->toBe(3);
});

test('has by slug scope', function () {
    Organization::factory()->create(['slug' => 'test-org']);
    Organization::factory()->create(['slug' => 'other-org']);

    $organization = Organization::bySlug('test-org')->first();

    expect($organization)->not->toBeNull()
        ->and($organization->slug)->toBe('test-org');
});

test('can get setting with default value', function () {
    $organization = Organization::factory()->create([
        'settings' => [
            'timezone' => 'America/New_York',
            'nested' => [
                'value' => 'test',
            ],
        ],
    ]);

    expect($organization->getSetting('timezone'))->toBe('America/New_York')
        ->and($organization->getSetting('nonexistent', 'default'))->toBe('default')
        ->and($organization->getSetting('nested.value'))->toBe('test');
});

test('can set setting value', function () {
    $organization = Organization::factory()->create(['settings' => []]);

    $organization->setSetting('timezone', 'Europe/London');
    $organization->save();

    $organization->refresh();

    expect($organization->getSetting('timezone'))->toBe('Europe/London');
});

test('can set nested setting value', function () {
    $organization = Organization::factory()->create(['settings' => []]);

    $organization->setSetting('notifications.email', true);
    $organization->save();

    $organization->refresh();

    expect($organization->getSetting('notifications.email'))->toBe(true);
});

test('can get contact detail with default value', function () {
    $organization = Organization::factory()->create([
        'contact_details' => [
            'email' => 'test@example.com',
            'phone' => '+1234567890',
        ],
    ]);

    expect($organization->getContactDetail('email'))->toBe('test@example.com')
        ->and($organization->getContactDetail('website', 'https://default.com'))->toBe('https://default.com');
});

test('can set contact detail value', function () {
    $organization = Organization::factory()->create(['contact_details' => []]);

    $organization->setContactDetail('email', 'new@example.com');
    $organization->save();

    $organization->refresh();

    expect($organization->getContactDetail('email'))->toBe('new@example.com');
});

test('settings use array object cast', function () {
    $organization = Organization::factory()->create([
        'settings' => ['key' => 'value'],
    ]);

    expect($organization->settings)->toBeInstanceOf(\ArrayObject::class)
        ->and($organization->settings['key'])->toBe('value');

    // Test mutation
    $organization->settings['new_key'] = 'new_value';
    $organization->save();
    $organization->refresh();

    expect($organization->settings['new_key'])->toBe('new_value');
});

test('contact details use array object cast', function () {
    $organization = Organization::factory()->create([
        'contact_details' => ['email' => 'test@example.com'],
    ]);

    expect($organization->contact_details)->toBeInstanceOf(\ArrayObject::class)
        ->and($organization->contact_details['email'])->toBe('test@example.com');

    // Test mutation
    $organization->contact_details['phone'] = '+1234567890';
    $organization->save();
    $organization->refresh();

    expect($organization->contact_details['phone'])->toBe('+1234567890');
});

test('can add address to organization', function () {
    $country = Country::factory()->create();
    $organization = Organization::factory()->create();

    $address = $organization->addAddress([
        'type' => Address::TYPE_HEADQUARTERS,
        'line_1' => '123 Test Street',
        'city' => 'Test City',
        'postal_code' => '12345',
        'country_id' => $country->id,
    ]);

    expect($address)->toBeInstanceOf(Address::class)
        ->and($organization->addresses()->count())->toBe(1)
        ->and($address->addressable_id)->toBe($organization->id);
});

test('can set primary address on organization', function () {
    $country = Country::factory()->create();
    $organization = Organization::factory()->create();

    $address = $organization->addAddress([
        'type' => Address::TYPE_HEADQUARTERS,
        'line_1' => '123 Test Street',
        'city' => 'Test City',
        'postal_code' => '12345',
        'country_id' => $country->id,
    ], true);

    expect($address->is_primary)->toBeTrue()
        ->and($organization->primaryAddress())->not->toBeNull();
});

test('can add user to organization', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'admin', ['permissions' => ['all']]);

    expect($organization->users()->count())->toBe(1)
        ->and($organization->users->first()->id)->toBe($user->id);

    $pivot = $organization->users()->first()->pivot;
    expect($pivot)->toBeInstanceOf(OrganizationUser::class)
        ->and($pivot->role)->toBe('admin')
        ->and($pivot->is_active)->toBeTrue()
        ->and($pivot->joined_at)->not->toBeNull();
});

test('can remove user from organization', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();

    $organization->addUser($user, 'member');
    $organization->removeUser($user);

    $pivot = $organization->users()->first()->pivot;
    expect($pivot->is_active)->toBeFalse()
        ->and($pivot->left_at)->not->toBeNull();
});

test('can check if user belongs to organization', function () {
    $organization = Organization::factory()->create();
    $user = createTestUser();
    $otherUser = createTestUser();

    $organization->addUser($user, 'member');

    expect($organization->hasUser($user))->toBeTrue()
        ->and($organization->hasUser($otherUser))->toBeFalse();
});

test('can get active users', function () {
    $organization = Organization::factory()->create();
    $activeUser = createTestUser();
    $inactiveUser = createTestUser();

    $organization->addUser($activeUser, 'member');
    $organization->addUser($inactiveUser, 'member');
    $organization->removeUser($inactiveUser);

    $activeUsers = $organization->activeUsers();

    expect($activeUsers->count())->toBe(1)
        ->and($activeUsers->first()->id)->toBe($activeUser->id);
});

test('factory inactive state works', function () {
    $organization = Organization::factory()->inactive()->create();

    expect($organization->is_active)->toBeFalse();
});

test('factory with slug state works', function () {
    $organization = Organization::factory()->withSlug('custom-slug')->create();

    expect($organization->slug)->toBe('custom-slug');
});

test('factory with vat state works', function () {
    $organization = Organization::factory()->withVat('GB123456789')->create();

    expect($organization->vat_number)->toBe('GB123456789');
});

test('factory with peppol state works', function () {
    $organization = Organization::factory()->withPeppol('9915:GB123456789')->create();

    expect($organization->peppol_id)->toBe('9915:GB123456789');
});

test('factory with contact details state works', function () {
    $organization = Organization::factory()->withContactDetails([
        'email' => 'custom@example.com',
    ])->create();

    expect($organization->getContactDetail('email'))->toBe('custom@example.com');
});

test('factory with settings state works', function () {
    $organization = Organization::factory()->withSettings([
        'custom_setting' => 'value',
    ])->create();

    expect($organization->getSetting('custom_setting'))->toBe('value');
});

test('factory small business state works', function () {
    $organization = Organization::factory()->smallBusiness()->create();

    expect($organization->name)->toContain('Ltd')
        ->and($organization->getSetting('timezone'))->toBe('UTC')
        ->and($organization->getSetting('currency'))->toBe('USD');
});

test('factory enterprise state works', function () {
    $organization = Organization::factory()->enterprise()->create();

    expect($organization->name)->toContain('Corporation')
        ->and($organization->vat_number)->not->toBeNull()
        ->and($organization->peppol_id)->not->toBeNull()
        ->and($organization->getSetting('multi_currency'))->toBeTrue()
        ->and($organization->getSetting('advanced_reporting'))->toBeTrue();
});
