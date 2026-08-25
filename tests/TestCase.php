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
use LaravelJsonApi\Laravel\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
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
            ServiceProvider::class,
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
     * Runs `migrate:fresh` (wipes the schema), then builds the entire test schema by
     * including each migration file and calling `->up()` in FK order: first the
     * test-specific tables (users — organization_user and deferred_api_requests have
     * FKs to it), then Sanctum's vendor table exactly as a host application would get
     * it, then the package's own `.php.stub` migrations.
     *
     * Everything goes through the include mechanism — deliberately NOT through
     * testbench's loadMigrationsFrom(): once RefreshDatabaseState::$migrated is true
     * (every pgsql test after the first), that helper switches to an immediate
     * MigrateProcessor->up() with a tearDown ->rollback() that DROPS the tables
     * mid-suite. And the Migrator itself only accepts `*.php` files, so the shipped
     * stubs could never be registered with it anyway (audit H20).
     *
     * The vendor spatie permission migrations are deliberately absent: the package
     * ships its own permission schema (UUID keys, organization_id team column) in
     * create_permission_tables.php.stub, and that is what must be exercised
     * (audit H20/C6).
     *
     * No return type: the signature must stay compatible with the untyped
     * RefreshDatabase::migrateDatabases() this overrides.
     *
     * @return void
     */
    protected function migrateDatabases()
    {
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());

        $files = [
            ...glob(__DIR__.'/database/migrations/*.php') ?: [],
            __DIR__.'/../vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php',
            ...static::packageMigrationStubs(),
        ];

        foreach ($files as $file) {
            $migration = include $file;
            $migration->up();
        }
    }

    /**
     * The package's migration stubs in FK dependency order:
     * currencies ← countries ← addresses ← organizations ← every org-scoped table.
     * (`users` comes from tests/database/migrations and already exists when this
     * list runs.)
     *
     * @return list<string>
     */
    public static function packageMigrationStubs(): array
    {
        $dir = __DIR__.'/../database/migrations/';

        return array_map(fn (string $name): string => $dir.$name.'.php.stub', [
            'create_currencies_table',
            'create_countries_table',
            'create_addresses_table',
            'create_organizations_table',
            'create_organization_user_table',
            'create_system_settings_table',
            'add_settings_to_organizations_table',
            'create_permission_tables',
            'create_deferred_endpoint_configs_table',
            'create_deferred_api_requests_table',
            'create_media_table',
            'create_activity_log_table',
            'create_notifications_table',
            'create_jobs_table',
        ]);
    }

    protected function defineRoutes($router): void
    {
        // Ensure package routes are loaded
        require __DIR__.'/../routes/api.php';

        // Add test route for deferred middleware testing
        $router->get('api/v1/test/{any}', function () {
            return response()->json(['message' => 'Test endpoint']);
        })->middleware(['auth:sanctum', 'org.resolve', 'deferred'])->where('any', '.*');
    }
}
