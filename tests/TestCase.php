<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests;

use Blafast\Foundation\BlafastServiceProvider;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends Orchestra
{
    // Composed HERE (not via Pest's uses()) so this class's migrateDatabases()
    // override wins: a trait applied to the generated Pest test classes would
    // shadow any method of the same name inherited from this parent.
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Blafast\\Foundation\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Migrations handle table creation via RefreshDatabase trait
    }

    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
            PermissionServiceProvider::class,
            // Loaded via package discovery in a real host; testbench discovers
            // nothing, and since the package no longer ships copies of their
            // configs (task 6/H21) these providers must merge their own.
            MediaLibraryServiceProvider::class,
            ActivitylogServiceProvider::class,
            BlafastServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        // Driver is selectable: sqlite in-memory by default (fast), real Postgres
        // when DB_CONNECTION=pgsql is set (see CLAUDE.md for the one-liner).
        if (env('DB_CONNECTION') === 'pgsql') {
            config()->set('database.default', 'pgsql');
            config()->set('database.connections.pgsql', array_merge(
                (array) config('database.connections.pgsql'),
                [
                    'driver' => 'pgsql',
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT', '55433'),
                    'database' => env('DB_DATABASE', 'blafast_fundation_test'),
                    'username' => env('DB_USERNAME', 'blafast'),
                    'password' => env('DB_PASSWORD', 'blafast'),
                ]
            ));
        } else {
            config()->set('database.default', 'testing');
            config()->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        }

        // Set application key for encryption (required for sessions)
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Configure authentication
        config()->set('auth.defaults.guard', 'api');
        config()->set('auth.guards.api', [
            'driver' => 'sanctum',
            'provider' => 'users',
        ]);
        config()->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => User::class,
        ]);
        config()->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);

        // Configure permissions
        config()->set('permission.teams', true);
        config()->set('permission.column_names.team_foreign_key', 'organization_id');
        config()->set('permission.column_names.model_morph_key', 'model_uuid');
        config()->set('permission.models.permission', Permission::class);
        config()->set('permission.models.role', Role::class);
    }

    /**
     * The package's own migrations are real timestamped `.php` files registered by
     * BlafastServiceProvider (discoversMigrations + runsMigrations) — exactly what a
     * host application gets. Here we only register the test-support paths, DIRECTLY
     * with the Migrator: testbench's loadMigrationsFrom() must not be used, because
     * once RefreshDatabaseState::$migrated is true (every pgsql test after the first)
     * it switches to an immediate MigrateProcessor->up() with a tearDown ->rollback()
     * that drops the tables mid-suite.
     *
     * RefreshDatabase's `migrate:fresh` then runs everything in filename order: the
     * test support tables (2026_01_04_…: personal_access_tokens with uuid morphs —
     * Sanctum's vendor migration ships bigint morphs that cannot hold uuid user ids
     * on Postgres — then users/addressable, FK targets of organization_user and
     * deferred_api_requests) → the package migrations (2026_08_25_…, FK-ordered).
     *
     * The vendor spatie permission migrations are deliberately absent: the package
     * ships its own permission schema (UUID keys, organization_id team column) and
     * that is what must be exercised (audit H20/C6).
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->app['migrator']->path(__DIR__.'/database/migrations');
    }

    protected function defineRoutes($router): void
    {
        // Ensure package routes are loaded
        require __DIR__.'/../routes/api.php';

        // Add test route for deferred middleware testing
        $router->get('api/v1/test/{any}', function () {
            return response()->json(['message' => 'Test endpoint']);
        })->middleware(['auth:sanctum', 'org.resolve', 'deferred'])->where('any', '.*');

        // Probe route for permission checks THROUGH the full middleware stack (task 5):
        // proves the org context resolved by org.resolve drives spatie's team id.
        $router->get('api/v1/test-can/{permission}', function (string $permission) {
            return response()->json(['can' => request()->user()->can($permission)]);
        })->middleware(['auth:sanctum', 'org.resolve']);

        // Probe routes for deferred execution (task 15): echo proves the executed
        // request sees the original user + org context; the other two exercise the
        // outcome classification (terminal 422, retryable 500-until-warm).
        $router->post('api/v1/test-deferred/echo', function () {
            return response()->json([
                'user_id' => request()->user()->id,
                'org_id' => organization_id(),
                'payload' => request()->input('data'),
            ]);
        })->middleware(['auth:sanctum', 'org.resolve', 'deferred']);

        $router->post('api/v1/test-deferred/unprocessable', function () {
            return response()->json(['error' => 'nope'], 422);
        })->middleware(['auth:sanctum', 'org.resolve', 'deferred']);

        $router->post('api/v1/test-deferred/flaky', function () {
            $hits = cache()->increment('test-deferred-flaky-hits');

            return $hits < 2
                ? response()->json(['error' => 'warming up'], 500)
                : response()->json(['ok' => true]);
        })->middleware(['auth:sanctum', 'org.resolve', 'deferred']);
    }
}
