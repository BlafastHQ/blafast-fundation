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
    }

    protected function getPackageProviders($app): array
    {
        return [
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

        // Create test users table
        $app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        // Run migrations
        $migration = include __DIR__.'/../database/migrations/2026_01_01_000001_create_currencies_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2026_01_01_000002_create_countries_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2026_01_01_000003_create_addresses_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2026_01_01_000004_create_organizations_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2026_01_01_000005_create_organization_user_table.php';
        $migration->up();

        // Create test table for Addressable trait testing
        $app['db']->connection()->getSchemaBuilder()->create('addressable_models', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }
}
