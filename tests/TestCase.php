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

        // Run migrations
        $migration = include __DIR__.'/../database/migrations/2026_01_01_000001_create_currencies_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2026_01_01_000002_create_countries_table.php';
        $migration->up();
    }
}
