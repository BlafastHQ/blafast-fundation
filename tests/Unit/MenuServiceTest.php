<?php

declare(strict_types=1);

use Blafast\Foundation\Services\MenuRegistry;
use Blafast\Foundation\Services\MenuService;
use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->registry = Mockery::mock(MenuRegistry::class);
    $this->cache = Mockery::mock(MetadataCacheService::class);
    $this->context = Mockery::mock(OrganizationContext::class);

    $this->service = new MenuService(
        $this->registry,
        $this->cache,
        $this->context
    );

    Config::set('blafast-fundation.cache.menu_ttl', 600);
});

afterEach(function () {
    Mockery::close();
});

test('getForUser returns cached menu', function () {
    $user = new User(['id' => 1]);

    $this->context->shouldReceive('id')->andReturn('123');
    $this->context->shouldReceive('hasContext')->andReturn(true);

    $this->registry->shouldReceive('all')->andReturn([]);

    $this->cache->shouldReceive('remember')
        ->once()
        ->with(
            'menu:user:1:org:123',
            Mockery::type('array'),
            Mockery::type('Closure'),
            600
        )
        ->andReturn([]);

    $menu = $this->service->getForUser($user);

    expect($menu)->toBeEmpty();
});

test('getForUser includes items without permission requirement', function () {
    $user = new User(['id' => 1]);

    $this->context->shouldReceive('id')->andReturn(null);
    $this->context->shouldReceive('hasContext')->andReturn(false);

    $menuItems = [
        new \Blafast\Foundation\Dto\MenuItem(
            label: 'dashboard',
            route: 'dashboard',
            permission: null
        ),
    ];

    $this->registry->shouldReceive('all')->andReturn($menuItems);

    $this->cache->shouldReceive('remember')
        ->andReturnUsing(fn ($key, $tags, $callback) => $callback());

    $menu = $this->service->getForUser($user);

    expect($menu)->toHaveCount(1)
        ->and($menu[0]->label)->toBe('dashboard');
});

test('getForUser filters items based on permissions', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 1;

    $user->shouldReceive('can')->with('view_admin')->andReturn(true);
    $user->shouldReceive('can')->with('view_secret')->andReturn(false);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);

    $this->context->shouldReceive('id')->andReturn(null);
    $this->context->shouldReceive('hasContext')->andReturn(false);

    $menuItems = [
        new \Blafast\Foundation\Dto\MenuItem(
            label: 'admin',
            route: 'admin',
            permission: 'view_admin'
        ),
        new \Blafast\Foundation\Dto\MenuItem(
            label: 'secret',
            route: 'secret',
            permission: 'view_secret'
        ),
    ];

    $this->registry->shouldReceive('all')->andReturn($menuItems);

    $this->cache->shouldReceive('remember')
        ->andReturnUsing(fn ($key, $tags, $callback) => $callback());

    $menu = $this->service->getForUser($user);

    expect($menu)->toHaveCount(1)
        ->and($menu[0]->label)->toBe('admin');
});

test('getForUser filters children recursively', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 1;

    $user->shouldReceive('can')->with('view_parent')->andReturn(true);
    $user->shouldReceive('can')->with('view_child1')->andReturn(true);
    $user->shouldReceive('can')->with('view_child2')->andReturn(false);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);

    $this->context->shouldReceive('id')->andReturn(null);
    $this->context->shouldReceive('hasContext')->andReturn(false);

    $menuItems = [
        new \Blafast\Foundation\Dto\MenuItem(
            label: 'parent',
            permission: 'view_parent',
            children: [
                new \Blafast\Foundation\Dto\MenuItem(
                    label: 'child1',
                    route: 'child1',
                    permission: 'view_child1'
                ),
                new \Blafast\Foundation\Dto\MenuItem(
                    label: 'child2',
                    route: 'child2',
                    permission: 'view_child2'
                ),
            ]
        ),
    ];

    $this->registry->shouldReceive('all')->andReturn($menuItems);

    $this->cache->shouldReceive('remember')
        ->andReturnUsing(fn ($key, $tags, $callback) => $callback());

    $menu = $this->service->getForUser($user);

    expect($menu)->toHaveCount(1)
        ->and($menu[0]->children)->toHaveCount(1)
        ->and($menu[0]->children[0]->label)->toBe('child1');
});

test('getForUser excludes parent without accessible children or route', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 1;

    $user->shouldReceive('can')->with('view_parent')->andReturn(true);
    $user->shouldReceive('can')->with('view_child')->andReturn(false);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);

    $this->context->shouldReceive('id')->andReturn(null);
    $this->context->shouldReceive('hasContext')->andReturn(false);

    $menuItems = [
        new \Blafast\Foundation\Dto\MenuItem(
            label: 'parent',
            permission: 'view_parent',
            // No route/url, only children
            children: [
                new \Blafast\Foundation\Dto\MenuItem(
                    label: 'child',
                    route: 'child',
                    permission: 'view_child'
                ),
            ]
        ),
    ];

    $this->registry->shouldReceive('all')->andReturn($menuItems);

    $this->cache->shouldReceive('remember')
        ->andReturnUsing(fn ($key, $tags, $callback) => $callback());

    $menu = $this->service->getForUser($user);

    expect($menu)->toBeEmpty();
});

test('getCacheKey includes user and organization', function () {
    $user = new User(['id' => 42]);

    $this->context->shouldReceive('id')->andReturn('org-123');

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('getCacheKey');
    $method->setAccessible(true);

    $key = $method->invoke($this->service, $user);

    expect($key)->toBe('menu:user:42:org:org-123');
});

test('getCacheKey uses global when no organization', function () {
    $user = new User(['id' => 42]);

    $this->context->shouldReceive('id')->andReturn(null);

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('getCacheKey');
    $method->setAccessible(true);

    $key = $method->invoke($this->service, $user);

    expect($key)->toBe('menu:user:42:org:global');
});

test('getCacheTags includes menu and user tags', function () {
    $user = new User(['id' => 42]);

    $this->context->shouldReceive('hasContext')->andReturn(true);
    $this->context->shouldReceive('id')->andReturn('org-123');

    $this->registry->shouldReceive('all')->andReturn([
        new \Blafast\Foundation\Dto\MenuItem(
            label: 'billing',
            tag: 'billing'
        ),
    ]);

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('getCacheTags');
    $method->setAccessible(true);

    $tags = $method->invoke($this->service, $user);

    expect($tags)->toContain('menu')
        ->and($tags)->toContain('user-42')
        ->and($tags)->toContain('org-org-123')
        ->and($tags)->toContain('billing');
});

test('getCacheTags includes child tags', function () {
    $user = new User(['id' => 1]);

    $this->context->shouldReceive('hasContext')->andReturn(false);

    $this->registry->shouldReceive('all')->andReturn([
        new \Blafast\Foundation\Dto\MenuItem(
            label: 'parent',
            tag: 'parent',
            children: [
                new \Blafast\Foundation\Dto\MenuItem(
                    label: 'child',
                    tag: 'parent.child'
                ),
            ]
        ),
    ]);

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('getCacheTags');
    $method->setAccessible(true);

    $tags = $method->invoke($this->service, $user);

    expect($tags)->toContain('parent')
        ->and($tags)->toContain('parent.child');
});

test('invalidateForUser invalidates cache', function () {
    $user = new User(['id' => 42]);

    $this->cache->shouldReceive('invalidateByTags')
        ->once()
        ->with(['menu', 'user-42']);

    $this->service->invalidateForUser($user);

    expect(true)->toBeTrue();
});
