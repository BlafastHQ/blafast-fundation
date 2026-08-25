<?php

declare(strict_types=1);

use Blafast\Foundation\BlafastServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Task 2: the package ships real timestamped migrations that `php artisan migrate`
 * alone can run — no vendor:publish step. The schema in THIS suite is built by
 * RefreshDatabase's `migrate:fresh` running the provider-registered files, so these
 * assertions prove the host contract, not a test-only include mechanism.
 */
it('creates the foundation tables with migrate alone — no publish step', function () {
    foreach ([
        'currencies', 'countries', 'addresses', 'organizations', 'organization_user',
        'system_settings', 'permissions', 'roles', 'deferred_endpoint_configs',
        'deferred_api_requests', 'media', 'activity_log', 'notifications', 'jobs',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing table: {$table}");
    }
});

it('registers each package migration with the Migrator exactly once', function () {
    $packageDir = realpath(__DIR__.'/../../database/migrations');

    $registered = array_filter(
        app('migrator')->paths(),
        fn (string $path): bool => realpath($path) === $packageDir
            || str_starts_with((string) realpath($path), $packageDir.DIRECTORY_SEPARATOR)
    );

    // discoversMigrations() registers per FILE (14), a directory registration would
    // be 1 — either way every migration file must be covered exactly once overall.
    expect(count($registered))->toBeGreaterThanOrEqual(1);

    $ran = DB::table('migrations')->pluck('migration');
    foreach (glob($packageDir.'/*.php') as $file) {
        $name = basename($file, '.php');
        expect($ran->filter(fn ($m) => $m === $name)->count())
            ->toBe(1, "migration {$name} ran ".$ran->filter(fn ($m) => $m === $name)->count().' times');
    }
});

it('keeps the migrations publish tag available (the fork path)', function () {
    $paths = ServiceProvider::pathsToPublish(BlafastServiceProvider::class, 'blafast-fundation-migrations');

    expect($paths)->not->toBeEmpty();
});
