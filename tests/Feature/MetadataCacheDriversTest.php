<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Tests\Fixtures\SalesOrderModel;
use Blafast\Foundation\Tests\Fixtures\User;
use Blafast\Foundation\Traits\ExposesApiStructure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

/**
 * Task 11 (C3/M1/M16): metadata caching across drivers WITHOUT tag support —
 * the array store supports tags, which is exactly why C3 shipped green.
 */
abstract class SharedStructureBase extends Model
{
    use ExposesApiStructure;
}

class FirstChildModel extends SharedStructureBase
{
    public static function apiStructure(): array
    {
        return ['slug' => 'first-child', 'label' => 'First', 'fields' => [['name' => 'a', 'label' => 'A', 'type' => 'string']]];
    }
}

class SecondChildModel extends SharedStructureBase
{
    public static function apiStructure(): array
    {
        return ['slug' => 'second-child', 'label' => 'Second', 'fields' => [['name' => 'b', 'label' => 'B', 'type' => 'string']]];
    }
}

function useFileCache(): void
{
    config()->set('cache.default', 'file');
    File::deleteDirectory(storage_path('framework/cache/data'));
}

it('serves /meta with the file cache driver — no tags 500 (C3)', function () {
    useFileCache();
    app(ModelRegistry::class)->register(Organization::class);

    $viewer = User::factory()->create();
    Permission::findOrCreate('list_organization', 'api');
    $viewer->givePermissionTo('list_organization');

    $this->actingAs($viewer, 'sanctum')
        ->getJson('/api/v1/meta/organization')
        ->assertOk()
        ->assertJsonPath('data.id', 'organization');
});

it('warms the metadata cache with the file driver (C3)', function () {
    useFileCache();
    app(ModelRegistry::class)->register(Organization::class);

    $this->artisan('blafast:cache:metadata warm')->assertSuccessful();
});

it('invalidates on a non-tagging driver via version-suffixed keys (M1)', function () {
    useFileCache();

    $cache = app(MetadataCacheService::class);
    $calls = 0;
    $compute = function () use (&$calls) {
        $calls++;

        return "value-{$calls}";
    };

    expect($cache->remember('probe', ['model-meta'], $compute))->toBe('value-1')
        // cached: no recompute
        ->and($cache->remember('probe', ['model-meta'], $compute))->toBe('value-1');

    // Invalidation used to be a silent no-op here (version counters nothing read).
    $cache->invalidateByTags(['model-meta']);

    expect($cache->remember('probe', ['model-meta'], $compute))->toBe('value-2');
});

it('gives each subclass of a shared base its own structure and slug (M16)', function () {
    // Order matters: the old shared static made the FIRST compiled structure win.
    expect(FirstChildModel::getApiSlug())->toBe('first-child')
        ->and(SecondChildModel::getApiSlug())->toBe('second-child')
        ->and(SecondChildModel::getApiStructure()['fields'][0]['name'])->toBe('b')
        ->and(FirstChildModel::getApiStructure()['fields'][0]['name'])->toBe('a');
});

it('closes the stale-advertisement window: a revoked exec grant disappears from /meta (M2)', function () {
    app(ModelRegistry::class)->register(SalesOrderModel::class);

    $user = User::factory()->create();
    Permission::findOrCreate('exec.sales-order-model', 'api');
    Permission::findOrCreate('list_sales-order-model', 'api');
    $user->givePermissionTo(['exec.sales-order-model', 'list_sales-order-model']);

    $first = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/meta/sales-order-model')
        ->assertOk()
        ->json();
    expect(json_encode($first))->toContain('approve');

    // Revoke → the spatie PermissionDetachedEvent fires (events_enabled) → the
    // listener invalidates this user's cached meta.
    $user->revokePermissionTo('exec.sales-order-model');
    $user->unsetRelation('roles')->unsetRelation('permissions');

    $second = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/meta/sales-order-model')
        ->assertOk()
        ->json();
    expect(json_encode($second))->not->toContain('"approve"');
});
