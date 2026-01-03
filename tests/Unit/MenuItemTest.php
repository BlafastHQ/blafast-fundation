<?php

declare(strict_types=1);

use Blafast\Foundation\Dto\MenuItem;

test('MenuItem can be created with required fields', function () {
    $item = new MenuItem(label: 'dashboard');

    expect($item->label)->toBe('dashboard')
        ->and($item->icon)->toBeNull()
        ->and($item->permission)->toBeNull()
        ->and($item->route)->toBeNull()
        ->and($item->url)->toBeNull()
        ->and($item->order)->toBe(100)
        ->and($item->tag)->toBeNull()
        ->and($item->children)->toBeEmpty();
});

test('MenuItem can be created with all fields', function () {
    $child = new MenuItem(label: 'sub-item');

    $item = new MenuItem(
        label: 'settings',
        icon: 'icon-settings',
        permission: 'view_settings',
        route: 'settings.index',
        url: null,
        order: 50,
        tag: 'settings',
        children: [$child],
    );

    expect($item->label)->toBe('settings')
        ->and($item->icon)->toBe('icon-settings')
        ->and($item->permission)->toBe('view_settings')
        ->and($item->route)->toBe('settings.index')
        ->and($item->order)->toBe(50)
        ->and($item->tag)->toBe('settings')
        ->and($item->children)->toHaveCount(1)
        ->and($item->children[0])->toBe($child);
});

test('fromArray creates MenuItem from array', function () {
    $data = [
        'label' => 'billing',
        'icon' => 'icon-billing',
        'permission' => 'view_billing',
        'route' => 'billing.index',
        'order' => 75,
        'tag' => 'billing',
    ];

    $item = MenuItem::fromArray($data);

    expect($item)->toBeInstanceOf(MenuItem::class)
        ->and($item->label)->toBe('billing')
        ->and($item->icon)->toBe('icon-billing')
        ->and($item->permission)->toBe('view_billing')
        ->and($item->route)->toBe('billing.index')
        ->and($item->order)->toBe(75)
        ->and($item->tag)->toBe('billing');
});

test('fromArray handles nested children', function () {
    $data = [
        'label' => 'parent',
        'children' => [
            [
                'label' => 'child1',
                'route' => 'child1.index',
            ],
            [
                'label' => 'child2',
                'route' => 'child2.index',
                'children' => [
                    ['label' => 'grandchild', 'route' => 'grandchild.index'],
                ],
            ],
        ],
    ];

    $item = MenuItem::fromArray($data);

    expect($item->children)->toHaveCount(2)
        ->and($item->children[0])->toBeInstanceOf(MenuItem::class)
        ->and($item->children[0]->label)->toBe('child1')
        ->and($item->children[1]->children)->toHaveCount(1)
        ->and($item->children[1]->children[0]->label)->toBe('grandchild');
});

test('fromArray uses default values for missing fields', function () {
    $data = ['label' => 'minimal'];

    $item = MenuItem::fromArray($data);

    expect($item->icon)->toBeNull()
        ->and($item->permission)->toBeNull()
        ->and($item->route)->toBeNull()
        ->and($item->url)->toBeNull()
        ->and($item->order)->toBe(100)
        ->and($item->tag)->toBeNull()
        ->and($item->children)->toBeEmpty();
});

test('toArray converts MenuItem to array', function () {
    $item = new MenuItem(
        label: 'dashboard',
        icon: 'icon-dashboard',
        permission: 'view_dashboard',
        route: 'dashboard',
        order: 10,
        tag: 'main.dashboard',
    );

    $array = $item->toArray();

    expect($array)->toBe([
        'label' => 'dashboard',
        'icon' => 'icon-dashboard',
        'permission' => 'view_dashboard',
        'route' => 'dashboard',
        'url' => null,
        'order' => 10,
        'tag' => 'main.dashboard',
        'children' => [],
    ]);
});

test('toArray handles nested children', function () {
    $grandchild = new MenuItem(label: 'grandchild');
    $child = new MenuItem(label: 'child', children: [$grandchild]);
    $parent = new MenuItem(label: 'parent', children: [$child]);

    $array = $parent->toArray();

    expect($array['children'])->toHaveCount(1)
        ->and($array['children'][0]['label'])->toBe('child')
        ->and($array['children'][0]['children'])->toHaveCount(1)
        ->and($array['children'][0]['children'][0]['label'])->toBe('grandchild');
});

test('withChildren creates new instance with updated children', function () {
    $original = new MenuItem(
        label: 'parent',
        icon: 'icon-parent',
        children: [new MenuItem(label: 'old-child')],
    );

    $newChildren = [
        new MenuItem(label: 'new-child-1'),
        new MenuItem(label: 'new-child-2'),
    ];

    $updated = $original->withChildren($newChildren);

    expect($updated)->not->toBe($original)
        ->and($updated->label)->toBe('parent')
        ->and($updated->icon)->toBe('icon-parent')
        ->and($updated->children)->toHaveCount(2)
        ->and($updated->children[0]->label)->toBe('new-child-1')
        ->and($updated->children[1]->label)->toBe('new-child-2')
        ->and($original->children)->toHaveCount(1)
        ->and($original->children[0]->label)->toBe('old-child');
});

test('MenuItem is readonly', function () {
    $item = new MenuItem(label: 'test');

    expect(function () use ($item) {
        $item->label = 'modified';
    })->toThrow(Error::class);
});

test('fromArray and toArray are reversible', function () {
    $original = [
        'label' => 'test',
        'icon' => 'icon-test',
        'permission' => 'test_permission',
        'route' => 'test.route',
        'url' => null,
        'order' => 50,
        'tag' => 'test.tag',
        'children' => [
            [
                'label' => 'child',
                'icon' => null,
                'permission' => null,
                'route' => 'child.route',
                'url' => null,
                'order' => 100,
                'tag' => null,
                'children' => [],
            ],
        ],
    ];

    $item = MenuItem::fromArray($original);
    $result = $item->toArray();

    expect($result)->toBe($original);
});

test('MenuItem supports external URLs', function () {
    $item = new MenuItem(
        label: 'external',
        url: 'https://example.com',
    );

    expect($item->url)->toBe('https://example.com')
        ->and($item->route)->toBeNull();
});

test('MenuItem can have both route and url null', function () {
    $item = new MenuItem(
        label: 'parent-only',
        children: [new MenuItem(label: 'child')],
    );

    expect($item->route)->toBeNull()
        ->and($item->url)->toBeNull()
        ->and($item->children)->not->toBeEmpty();
});
