<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Role;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Proves the package's OWN permission schema ran — not the vendor spatie
 * migrations (which have bigint auto-increment ids and, unconfigured, no
 * organization_id team column). See Task 1 / audit H20.
 */
it('creates the package permission tables with organization_id team columns', function () {
    expect(Schema::hasTable('permissions'))->toBeTrue()
        ->and(Schema::hasTable('roles'))->toBeTrue()
        ->and(Schema::hasColumns('permissions', ['id', 'organization_id']))->toBeTrue()
        ->and(Schema::hasColumns('roles', ['id', 'organization_id']))->toBeTrue()
        ->and(Schema::hasColumns('model_has_roles', ['organization_id', 'model_uuid']))->toBeTrue()
        ->and(Schema::hasColumns('model_has_permissions', ['organization_id', 'model_uuid']))->toBeTrue();
});

it('gives roles a uuid primary key, not a vendor auto-increment id', function () {
    $role = Role::create(['name' => 'schema-probe', 'guard_name' => 'api']);

    expect(Str::isUuid((string) $role->id))->toBeTrue();
});
