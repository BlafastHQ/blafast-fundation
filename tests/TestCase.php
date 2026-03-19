<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests;

use Blafast\Foundation\BlafastServiceProvider;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Sanctum\SanctumServiceProvider;
use LaravelJsonApi\Laravel\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends Orchestra
{
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
        $migration = include __DIR__.'/../database/migrations/create_blafast_foundation_tables.php.stub';
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

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

    protected function defineDatabaseMigrations(): void
    {
        // Load Spatie Permission migrations first
        $this->loadMigrationsFrom(__DIR__.'/../vendor/spatie/laravel-permission/database/migrations');

        // Load package migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load test-specific migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
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
