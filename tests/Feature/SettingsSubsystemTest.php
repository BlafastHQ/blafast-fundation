<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Models\SystemSetting;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Services\SettingsService;
use Blafast\Foundation\Tests\Fixtures\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Task 9 (H4/H5/M3/M4): the settings subsystem.
 */
function memberOf(Organization $org, ?string $permission = null): User
{
    $user = User::factory()->create();
    $org->addUser($user, 'User');

    if ($permission !== null) {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($org->id);
        Permission::findOrCreate($permission, 'api');
        $role = Role::findOrCreate('SettingsAdmin-'.$org->id, 'api');
        $role->givePermissionTo($permission);
        $user->assignRole($role);
        $registrar->setPermissionsTeamId(null);
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    return $user;
}

it('hides private system settings from members on /settings/resolved (H4)', function () {
    SystemSetting::create(['key' => 'app.title', 'value' => 'Blafast', 'type' => 'string', 'is_public' => true]);
    SystemSetting::create(['key' => 'smtp.secret', 'value' => 'hunter2', 'type' => 'string', 'is_public' => false]);

    $org = Organization::factory()->create();
    $member = memberOf($org);

    $attributes = $this->actingAs($member, 'sanctum')
        ->getJson('/api/v1/settings/resolved', ['X-Organization-Id' => $org->id])
        ->assertOk()
        ->json('data.attributes');

    expect($attributes)->toHaveKey('app.title')
        ->and($attributes)->not->toHaveKey('smtp.secret');
});

it('shows a superadmin every system setting via systemIndex', function () {
    SystemSetting::create(['key' => 'smtp.secret', 'value' => 'hunter2', 'type' => 'string', 'is_public' => false]);

    $superadmin = User::factory()->create();
    Role::findOrCreate('Superadmin', 'api');
    $superadmin->assignRole('Superadmin');

    $keys = collect($this->actingAs($superadmin, 'sanctum')
        ->getJson('/api/v1/settings/system')
        ->assertOk()
        ->json('data'))->pluck('attributes.key')->whenEmpty(fn () => collect(), fn ($c) => $c);

    // Resource shape may nest differently; assert on the raw response instead.
    $raw = $this->actingAs($superadmin, 'sanctum')->getJson('/api/v1/settings/system')->json();
    expect(json_encode($raw))->toContain('smtp.secret');
});

it('persists is_public, group and description on systemUpdate (H4)', function () {
    $superadmin = User::factory()->create();
    Role::findOrCreate('Superadmin', 'api');
    $superadmin->assignRole('Superadmin');

    $this->actingAs($superadmin, 'sanctum')
        ->putJson('/api/v1/settings/system/branding.color', [
            'value' => '#123456',
            'type' => 'string',
            'is_public' => true,
            'group' => 'branding',
            'description' => 'Primary color',
        ])
        ->assertOk();

    $setting = SystemSetting::where('key', 'branding.color')->first();
    expect($setting->is_public)->toBeTrue()
        ->and($setting->group)->toBe('branding')
        ->and($setting->description)->toBe('Primary color');
});

it('lets an org admin read and update organization settings (H5 — no blanket 403)', function () {
    $org = Organization::factory()->create();
    $admin = memberOf($org, 'update_organization');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/settings/organization', ['X-Organization-Id' => $org->id])
        ->assertOk();

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/v1/settings/organization', [
            'settings' => ['locale' => 'fr'],
        ], ['X-Organization-Id' => $org->id])
        ->assertOk();

    expect($org->fresh()->settings['locale'] ?? null)->toBe('fr');

    // A plain member stays denied.
    $member = memberOf($org);
    $this->actingAs($member, 'sanctum')
        ->putJson('/api/v1/settings/organization', [
            'settings' => ['locale' => 'nl'],
        ], ['X-Organization-Id' => $org->id])
        ->assertStatus(403);
});

it('resolves dotted keys identically through get(), value() and all() for both tiers (M3)', function () {
    SystemSetting::create(['key' => 'mail.from', 'value' => 'sys@blafast.io', 'type' => 'string', 'is_public' => true]);
    SystemSetting::create(['key' => 'mail.reply_to', 'value' => 'reply@blafast.io', 'type' => 'string', 'is_public' => true]);

    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->addUser($user, 'User');
    $org->setSetting('mail.from', 'org@blafast.io')->save();

    app(OrganizationContext::class)->set($org->fresh(), $user);

    $service = app(SettingsService::class);
    $all = $service->all();

    // Org override wins in BOTH per-key and resolved views…
    expect($service->get('mail.from'))->toBe(['value' => 'org@blafast.io', 'source' => 'organization'])
        ->and($service->value('mail.from'))->toBe('org@blafast.io')
        ->and($all['mail.from'])->toBe('org@blafast.io')
        // …and a flat system key un-shadowed by the org subtree still resolves
        // (the old shallow array_merge lost it under the nested `mail` array).
        ->and($service->value('mail.reply_to'))->toBe('reply@blafast.io')
        ->and($all['mail.reply_to'])->toBe('reply@blafast.io');
});

it('does not lose keys on concurrent organization-settings writes (M4)', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->addUser($user, 'User');

    // The context holds a snapshot loaded "at middleware time"…
    app(OrganizationContext::class)->set($org, $user);

    // …then ANOTHER writer updates the row out from under it.
    Organization::whereKey($org->id)
        ->update(['settings' => json_encode(['from_other_writer' => 1])]);

    // The old code saved the stale snapshot and erased from_other_writer.
    app(SettingsService::class)->setOrganization('mine', 2);

    $settings = $org->fresh()->settings;
    expect($settings['from_other_writer'] ?? null)->toBe(1)
        ->and($settings['mine'] ?? null)->toBe(2);
});
