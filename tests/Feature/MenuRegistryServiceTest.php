<?php

declare(strict_types=1);

use Blafast\Foundation\Dto\MenuItem;
use Blafast\Foundation\Facades\MenuRegistry as MenuRegistryFacade;
use Blafast\Foundation\Services\MenuRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('MenuRegistry is registered as singleton', function () {
    $registry1 = app(MenuRegistry::class);
    $registry2 = app(MenuRegistry::class);

    expect($registry1)->toBe($registry2);
});

test('MenuRegistry singleton maintains state across resolutions', function () {
    $registry = app(MenuRegistry::class);
    $registry->add(['label' => 'test-item']);

    $resolvedRegistry = app(MenuRegistry::class);
    $items = $resolvedRegistry->all();

    expect($items)->toHaveCount(1)
        ->and($items[0]->label)->toBe('test-item');
});

test('MenuRegistry facade works', function () {
    MenuRegistryFacade::add(['label' => 'dashboard', 'route' => 'dashboard']);

    $items = MenuRegistryFacade::all();

    expect($items)->toHaveCount(1)
        ->and($items[0]->label)->toBe('dashboard');
});

test('facade and service container return same instance', function () {
    MenuRegistryFacade::add(['label' => 'from-facade']);

    $service = app(MenuRegistry::class);
    $items = $service->all();

    expect($items)->toHaveCount(1)
        ->and($items[0]->label)->toBe('from-facade');
});

test('multiple modules can register menu items', function () {
    // Simulate Module 1
    $registry = app(MenuRegistry::class);
    $registry->add([
        'label' => 'module1',
        'tag' => 'module1',
        'order' => 100,
    ]);

    // Simulate Module 2
    $registry->add([
        'label' => 'module2',
        'tag' => 'module2',
        'order' => 200,
    ]);

    // Simulate Module 3
    $registry->add([
        'label' => 'module3',
        'tag' => 'module3',
        'order' => 50,
    ]);

    $items = $registry->all();

    expect($items)->toHaveCount(3)
        ->and($items[0]->label)->toBe('module3')
        ->and($items[1]->label)->toBe('module1')
        ->and($items[2]->label)->toBe('module2');
});

test('modules can extend existing menu tags', function () {
    $registry = app(MenuRegistry::class);

    // Core module registers base billing menu
    $registry->add([
        'label' => 'billing',
        'tag' => 'billing',
        'icon' => 'icon-billing',
        'permission' => 'view_billing',
        'children' => [
            ['label' => 'invoices', 'tag' => 'billing.invoices', 'route' => 'invoices.index'],
        ],
    ]);

    // Another module extends billing menu
    $registry->add([
        'label' => 'billing',
        'tag' => 'billing',
        'children' => [
            ['label' => 'payments', 'tag' => 'billing.payments', 'route' => 'payments.index'],
        ],
    ]);

    // Third module extends specific child
    $registry->add([
        'label' => 'billing',
        'tag' => 'billing',
        'children' => [
            [
                'label' => 'invoices-extended',
                'tag' => 'billing.invoices',
                'permission' => 'list_invoices',
            ],
        ],
    ]);

    $item = $registry->getByTag('billing');

    expect($item->children)->toHaveCount(2)
        ->and($item->children[0]->label)->toBe('invoices-extended')
        ->and($item->children[0]->route)->toBe('invoices.index')
        ->and($item->children[0]->permission)->toBe('list_invoices')
        ->and($item->children[1]->label)->toBe('payments');
});

test('complex module interaction scenario', function () {
    $registry = app(MenuRegistry::class);

    // Foundation module: Basic admin menu
    $registry->add([
        'label' => 'admin',
        'tag' => 'admin',
        'icon' => 'icon-admin',
        'permission' => 'access_admin',
        'order' => 1000,
        'children' => [
            ['label' => 'dashboard', 'tag' => 'admin.dashboard', 'route' => 'admin.dashboard'],
        ],
    ]);

    // Users module: Extends admin with users section
    $registry->add([
        'label' => 'admin',
        'tag' => 'admin',
        'children' => [
            [
                'label' => 'users',
                'tag' => 'admin.users',
                'route' => 'admin.users.index',
                'permission' => 'view_users',
                'order' => 100,
            ],
        ],
    ]);

    // Roles module: Extends admin with roles section
    $registry->add([
        'label' => 'admin',
        'tag' => 'admin',
        'children' => [
            [
                'label' => 'roles',
                'tag' => 'admin.roles',
                'route' => 'admin.roles.index',
                'permission' => 'view_roles',
                'order' => 200,
            ],
        ],
    ]);

    // Settings module: Extends admin with settings
    $registry->add([
        'label' => 'admin',
        'tag' => 'admin',
        'children' => [
            [
                'label' => 'settings',
                'tag' => 'admin.settings',
                'route' => 'admin.settings.index',
                'order' => 300,
            ],
        ],
    ]);

    $admin = $registry->getByTag('admin');

    expect($admin->children)->toHaveCount(4)
        ->and($admin->children[0]->label)->toBe('admin.dashboard')
        ->and($admin->children[1]->label)->toBe('users')
        ->and($admin->children[2]->label)->toBe('roles')
        ->and($admin->children[3]->label)->toBe('settings');
});

test('facade addMany works correctly', function () {
    MenuRegistryFacade::addMany([
        ['label' => 'item1', 'order' => 100],
        ['label' => 'item2', 'order' => 50],
        ['label' => 'item3', 'order' => 200],
    ]);

    $items = MenuRegistryFacade::all();

    expect($items)->toHaveCount(3)
        ->and($items[0]->label)->toBe('item2')
        ->and($items[1]->label)->toBe('item1')
        ->and($items[2]->label)->toBe('item3');
});

test('facade hasTag and getByTag work correctly', function () {
    MenuRegistryFacade::add(['label' => 'billing', 'tag' => 'billing']);

    expect(MenuRegistryFacade::hasTag('billing'))->toBeTrue()
        ->and(MenuRegistryFacade::hasTag('nonexistent'))->toBeFalse()
        ->and(MenuRegistryFacade::getByTag('billing'))->toBeInstanceOf(MenuItem::class)
        ->and(MenuRegistryFacade::getByTag('nonexistent'))->toBeNull();
});

test('facade flushTag works correctly', function () {
    MenuRegistryFacade::add(['label' => 'test', 'tag' => 'test']);

    expect(MenuRegistryFacade::hasTag('test'))->toBeTrue();

    MenuRegistryFacade::flushTag('test');

    expect(MenuRegistryFacade::hasTag('test'))->toBeFalse();
});

test('facade clear works correctly', function () {
    MenuRegistryFacade::add(['label' => 'item1']);
    MenuRegistryFacade::add(['label' => 'item2', 'tag' => 'tagged']);

    expect(MenuRegistryFacade::all())->toHaveCount(2);

    MenuRegistryFacade::clear();

    expect(MenuRegistryFacade::all())->toBeEmpty();
});

test('method chaining works with facade', function () {
    MenuRegistryFacade::add(['label' => 'item1'])
        ->add(['label' => 'item2'])
        ->add(['label' => 'item3']);

    expect(MenuRegistryFacade::all())->toHaveCount(3);
});

test('real-world billing module scenario', function () {
    $registry = app(MenuRegistry::class);

    // Billing module registers its menu
    $registry->add([
        'label' => 'billing',
        'icon' => 'icon-invoice',
        'permission' => 'view_billing_module',
        'tag' => 'billing',
        'order' => 50,
        'children' => [
            [
                'label' => 'invoices',
                'route' => 'invoices.index',
                'permission' => 'list_invoices',
                'tag' => 'billing.invoices',
                'order' => 100,
            ],
            [
                'label' => 'customers',
                'route' => 'customers.index',
                'permission' => 'list_customers',
                'tag' => 'billing.customers',
                'order' => 200,
            ],
        ],
    ]);

    // Subscription module extends billing menu
    $registry->add([
        'label' => 'billing',
        'tag' => 'billing',
        'children' => [
            [
                'label' => 'subscriptions',
                'route' => 'subscriptions.index',
                'permission' => 'list_subscriptions',
                'tag' => 'billing.subscriptions',
                'order' => 150,
            ],
        ],
    ]);

    $billing = $registry->getByTag('billing');

    expect($billing->children)->toHaveCount(3)
        ->and($billing->children[0]->label)->toBe('invoices')
        ->and($billing->children[1]->label)->toBe('subscriptions')
        ->and($billing->children[2]->label)->toBe('customers');
});
