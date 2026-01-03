<?php

declare(strict_types=1);

use Blafast\Foundation\Dto\MenuItem;
use Blafast\Foundation\Services\MenuRegistry;

beforeEach(function () {
    $this->registry = new MenuRegistry;
});

afterEach(function () {
    $this->registry->clear();
});

test('add registers a menu item from array', function () {
    $this->registry->add([
        'label' => 'dashboard',
        'icon' => 'icon-dashboard',
        'route' => 'dashboard',
    ]);

    $items = $this->registry->all();

    expect($items)->toHaveCount(1)
        ->and($items[0])->toBeInstanceOf(MenuItem::class)
        ->and($items[0]->label)->toBe('dashboard')
        ->and($items[0]->icon)->toBe('icon-dashboard')
        ->and($items[0]->route)->toBe('dashboard');
});

test('add registers a MenuItem instance', function () {
    $item = new MenuItem(
        label: 'settings',
        icon: 'icon-settings',
        route: 'settings.index'
    );

    $this->registry->add($item);

    $items = $this->registry->all();

    expect($items)->toHaveCount(1)
        ->and($items[0])->toBe($item);
});

test('addMany registers multiple items', function () {
    $this->registry->addMany([
        ['label' => 'dashboard', 'route' => 'dashboard'],
        ['label' => 'settings', 'route' => 'settings'],
        ['label' => 'users', 'route' => 'users.index'],
    ]);

    $items = $this->registry->all();

    expect($items)->toHaveCount(3);
});

test('items are sorted by order', function () {
    $this->registry->add(['label' => 'third', 'order' => 300]);
    $this->registry->add(['label' => 'first', 'order' => 100]);
    $this->registry->add(['label' => 'second', 'order' => 200]);

    $items = $this->registry->all();

    expect($items[0]->label)->toBe('first')
        ->and($items[1]->label)->toBe('second')
        ->and($items[2]->label)->toBe('third');
});

test('tagged items are stored separately', function () {
    $this->registry->add(['label' => 'billing', 'tag' => 'billing']);
    $this->registry->add(['label' => 'untagged']);

    expect($this->registry->hasTag('billing'))->toBeTrue()
        ->and($this->registry->getByTag('billing'))->toBeInstanceOf(MenuItem::class)
        ->and($this->registry->getByTag('billing')->label)->toBe('billing');
});

test('duplicate tags merge items', function () {
    $this->registry->add([
        'label' => 'billing',
        'tag' => 'billing',
        'icon' => 'icon-billing',
        'order' => 50,
    ]);

    $this->registry->add([
        'label' => 'billing-updated',
        'tag' => 'billing',
        'permission' => 'view_billing',
    ]);

    $item = $this->registry->getByTag('billing');

    expect($item->label)->toBe('billing-updated')
        ->and($item->icon)->toBe('icon-billing')
        ->and($item->permission)->toBe('view_billing')
        ->and($item->order)->toBe(50);
});

test('merge preserves children from both items', function () {
    $this->registry->add([
        'label' => 'billing',
        'tag' => 'billing',
        'children' => [
            ['label' => 'invoices', 'tag' => 'billing.invoices'],
        ],
    ]);

    $this->registry->add([
        'label' => 'billing',
        'tag' => 'billing',
        'children' => [
            ['label' => 'payments', 'tag' => 'billing.payments'],
        ],
    ]);

    $item = $this->registry->getByTag('billing');

    expect($item->children)->toHaveCount(2)
        ->and($item->children[0]->label)->toBe('invoices')
        ->and($item->children[1]->label)->toBe('payments');
});

test('tagged children merge correctly', function () {
    $this->registry->add([
        'label' => 'settings',
        'tag' => 'settings',
        'children' => [
            [
                'label' => 'general',
                'tag' => 'settings.general',
                'route' => 'settings.general',
            ],
        ],
    ]);

    $this->registry->add([
        'label' => 'settings',
        'tag' => 'settings',
        'children' => [
            [
                'label' => 'general-updated',
                'tag' => 'settings.general',
                'icon' => 'icon-general',
            ],
        ],
    ]);

    $item = $this->registry->getByTag('settings');

    expect($item->children)->toHaveCount(1)
        ->and($item->children[0]->label)->toBe('general-updated')
        ->and($item->children[0]->route)->toBe('settings.general')
        ->and($item->children[0]->icon)->toBe('icon-general');
});

test('children are sorted by order', function () {
    $this->registry->add([
        'label' => 'settings',
        'children' => [
            ['label' => 'third', 'order' => 300],
            ['label' => 'first', 'order' => 100],
            ['label' => 'second', 'order' => 200],
        ],
    ]);

    $items = $this->registry->all();
    $children = $items[0]->children;

    expect($children[0]->label)->toBe('first')
        ->and($children[1]->label)->toBe('second')
        ->and($children[2]->label)->toBe('third');
});

test('nested children are sorted recursively', function () {
    $this->registry->add([
        'label' => 'parent',
        'children' => [
            [
                'label' => 'child',
                'children' => [
                    ['label' => 'grandchild-b', 'order' => 200],
                    ['label' => 'grandchild-a', 'order' => 100],
                ],
            ],
        ],
    ]);

    $items = $this->registry->all();
    $grandchildren = $items[0]->children[0]->children;

    expect($grandchildren[0]->label)->toBe('grandchild-a')
        ->and($grandchildren[1]->label)->toBe('grandchild-b');
});

test('flushTag removes tagged item', function () {
    $this->registry->add(['label' => 'billing', 'tag' => 'billing']);

    expect($this->registry->hasTag('billing'))->toBeTrue();

    $this->registry->flushTag('billing');

    expect($this->registry->hasTag('billing'))->toBeFalse()
        ->and($this->registry->getByTag('billing'))->toBeNull();
});

test('clear removes all items', function () {
    $this->registry->add(['label' => 'item1']);
    $this->registry->add(['label' => 'item2', 'tag' => 'tagged']);

    expect($this->registry->all())->toHaveCount(2);

    $this->registry->clear();

    expect($this->registry->all())->toBeEmpty()
        ->and($this->registry->hasTag('tagged'))->toBeFalse();
});

test('order defaults to 100', function () {
    $this->registry->add(['label' => 'item']);

    $items = $this->registry->all();

    expect($items[0]->order)->toBe(100);
});

test('tagged and untagged items are returned together', function () {
    $this->registry->add(['label' => 'tagged', 'tag' => 'test', 'order' => 50]);
    $this->registry->add(['label' => 'untagged-1', 'order' => 100]);
    $this->registry->add(['label' => 'untagged-2', 'order' => 200]);

    $items = $this->registry->all();

    expect($items)->toHaveCount(3)
        ->and($items[0]->label)->toBe('tagged')
        ->and($items[1]->label)->toBe('untagged-1')
        ->and($items[2]->label)->toBe('untagged-2');
});

test('all supports hierarchical menu structures', function () {
    $this->registry->add([
        'label' => 'admin',
        'tag' => 'admin',
        'children' => [
            [
                'label' => 'users',
                'route' => 'admin.users.index',
                'children' => [
                    ['label' => 'create', 'route' => 'admin.users.create'],
                    ['label' => 'list', 'route' => 'admin.users.index'],
                ],
            ],
            [
                'label' => 'settings',
                'route' => 'admin.settings.index',
            ],
        ],
    ]);

    $items = $this->registry->all();

    expect($items)->toHaveCount(1)
        ->and($items[0]->children)->toHaveCount(2)
        ->and($items[0]->children[0]->children)->toHaveCount(2);
});

test('menu item supports permission field', function () {
    $this->registry->add([
        'label' => 'admin',
        'permission' => 'access_admin_panel',
    ]);

    $items = $this->registry->all();

    expect($items[0]->permission)->toBe('access_admin_panel');
});

test('menu item supports both route and url', function () {
    $this->registry->add([
        'label' => 'external',
        'url' => 'https://example.com',
    ]);

    $this->registry->add([
        'label' => 'internal',
        'route' => 'dashboard',
    ]);

    $items = $this->registry->all();

    expect($items[0]->url)->toBe('https://example.com')
        ->and($items[0]->route)->toBeNull()
        ->and($items[1]->route)->toBe('dashboard')
        ->and($items[1]->url)->toBeNull();
});

test('merge with order 100 preserves existing order', function () {
    $this->registry->add([
        'label' => 'test',
        'tag' => 'test',
        'order' => 50,
    ]);

    $this->registry->add([
        'label' => 'test-updated',
        'tag' => 'test',
        // order not specified (defaults to 100)
    ]);

    $item = $this->registry->getByTag('test');

    expect($item->order)->toBe(50);
});
