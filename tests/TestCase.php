<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests;

use Blafast\Foundation\BlafastServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Blafast\\Foundation\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Ensure test tables exist
        $this->ensureTestTablesExist();
    }

    protected function ensureTestTablesExist(): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        // Create personal_access_tokens table for Sanctum
        if (! $schema->hasTable('personal_access_tokens')) {
            $schema->create('personal_access_tokens', function ($table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        // Create test users table
        if (! $schema->hasTable('users')) {
            $schema->create('users', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }

        // Create test table for Addressable trait testing
        if (! $schema->hasTable('addressable_models')) {
            $schema->create('addressable_models', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->timestamps();
            });
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Laravel\Sanctum\SanctumServiceProvider::class,
            BlafastServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure authentication
        config()->set('auth.defaults.guard', 'api');
        config()->set('auth.guards.api', [
            'driver' => 'sanctum',
            'provider' => 'users',
        ]);
        config()->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => \Blafast\Foundation\Tests\Fixtures\User::class,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Load package migrations - they are auto-loaded by the service provider via runsMigrations()
        // We just need to ensure they run with RefreshDatabase
    }

    protected function afterRefreshingDatabase()
    {
        // Create personal_access_tokens table for Sanctum
        if (! $this->app['db']->connection()->getSchemaBuilder()->hasTable('personal_access_tokens')) {
            $this->app['db']->connection()->getSchemaBuilder()->create('personal_access_tokens', function ($table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        // Create test users table
        if (! $this->app['db']->connection()->getSchemaBuilder()->hasTable('users')) {
            $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }

        // Create test table for Addressable trait testing
        if (! $this->app['db']->connection()->getSchemaBuilder()->hasTable('addressable_models')) {
            $this->app['db']->connection()->getSchemaBuilder()->create('addressable_models', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->timestamps();
            });
        }
    }
}
