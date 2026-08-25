<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Address;
use Blafast\Foundation\Models\Country;
use Blafast\Foundation\Models\Currency;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Providers\DynamicRouteServiceProvider;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Tests\Fixtures\AddressableModel;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Task 14 (H13/H15): ?include= actually serializes, both endpoints validate
 * includes identically, and everything /meta advertises works when used.
 */
beforeEach(function () {
    app()->register(DynamicRouteServiceProvider::class);
    app(ModelRegistry::class)->register(Organization::class);

    Route::prefix('api/v1')
        ->middleware('api')
        ->group(function () {
            Route::dynamicResource(Organization::class);
        });

    $viewer = User::factory()->create();
    foreach (['list_organization', 'view_organization'] as $p) {
        Permission::findOrCreate($p, 'api');
    }
    $viewer->givePermissionTo(['list_organization', 'view_organization']);
    Role::findOrCreate('Superadmin', 'api');
    $viewer->assignRole('Superadmin');
    $viewer->unsetRelation('roles')->unsetRelation('permissions');
    test()->actingAs($viewer, 'sanctum');
});

function orgWithAddress(): Organization
{
    $currency = Currency::factory()->create();
    $country = Country::factory()->create(['currency_id' => $currency->id]);
    $address = Address::factory()->create(['country_id' => $country->id, 'city' => 'Brussels']);

    $org = Organization::factory()->create();
    $org->forceFill(['address_id' => $address->id])->save();

    return $org->fresh();
}

it('returns related data for ?include=users — the payload differs from the plain call (H13)', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->addUser($user, 'User');

    $plain = $this->getJson("/api/v1/organization/{$org->id}")->assertOk()->json();
    $included = $this->getJson("/api/v1/organization/{$org->id}?include=users")->assertOk()->json();

    expect($included)->not->toBe($plain)
        ->and($included['data']['relationships']['users']['data'][0]['id'])->toBe($user->id);
});

it('rejects an unknown include identically on index and show', function () {
    $org = Organization::factory()->create();

    $index = $this->getJson('/api/v1/organization?include=bogus')->status();
    $show = $this->getJson("/api/v1/organization/{$org->id}?include=bogus")->status();

    expect($index)->toBe($show)->and($index)->toBe(400);
});

it('includes primaryAddress and filters on the advertised relation name with valid SQL (H15)', function () {
    $org = orgWithAddress();

    // The include serializes the real BelongsTo relation.
    $this->getJson("/api/v1/organization/{$org->id}?include=primaryAddress")
        ->assertOk()
        ->assertJsonPath('data.relationships.primaryAddress.data.attributes.city', 'Brussels');

    // /meta advertises the registered name…
    $advertised = collect($this->getJson('/api/v1/meta/organization')->assertOk()
        ->json('data.attributes.filters'))->pluck('field');
    expect($advertised)->toContain('primaryAddress.city');

    // …and using it produces valid SQL against a real column.
    $sql = [];
    DB::listen(function ($q) use (&$sql) {
        $sql[] = $q->sql;
    });

    $this->getJson('/api/v1/organization?filter[primaryAddress.city]=Brussels')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    expect(collect($sql)->first(fn ($s) => str_contains($s, 'city')))->not->toBeNull();
});

it('cannot advertise primaryAddress as an include from the bare Addressable trait (H15b)', function () {
    // The trait ships a getPrimaryAddress() HELPER; no relation-shaped
    // primaryAddress() method exists to be mistaken for an includable relation.
    expect(method_exists(AddressableModel::class, 'getPrimaryAddress'))->toBeTrue()
        ->and(method_exists(AddressableModel::class, 'primaryAddress'))->toBeFalse();
});
