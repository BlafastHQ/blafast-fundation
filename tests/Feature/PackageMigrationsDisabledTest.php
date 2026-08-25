<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Feature;

use Blafast\Foundation\BlafastServiceProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use LaravelJsonApi\Laravel\ServiceProvider as JsonApiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

/**
 * Task 2: FOUNDATION_RUN_MIGRATIONS=false stops the package registering its
 * migrations with the Migrator (the opt-out for hosts that publish and fork them)
 * while the publish tag itself stays available.
 *
 * Deliberately NOT extending the package TestCase: this app must boot with the
 * flag off, without RefreshDatabase (so it cannot poison the shared
 * RefreshDatabaseState for the rest of the suite) and without touching the DB.
 */
class PackageMigrationsDisabledTest extends Orchestra
{
    protected function setUp(): void
    {
        putenv('FOUNDATION_RUN_MIGRATIONS=false');
        $_ENV['FOUNDATION_RUN_MIGRATIONS'] = 'false';
        $_SERVER['FOUNDATION_RUN_MIGRATIONS'] = 'false';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        putenv('FOUNDATION_RUN_MIGRATIONS');
        unset($_ENV['FOUNDATION_RUN_MIGRATIONS'], $_SERVER['FOUNDATION_RUN_MIGRATIONS']);
    }

    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
            PermissionServiceProvider::class,
            JsonApiServiceProvider::class,
            BlafastServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function test_the_package_registers_no_migrations_when_run_migrations_is_off(): void
    {
        $packageDir = realpath(__DIR__.'/../../database/migrations');

        $registered = array_filter(
            $this->app['migrator']->paths(),
            fn (string $path): bool => str_starts_with((string) realpath($path), (string) $packageDir)
        );

        $this->assertSame([], array_values($registered));
    }

    public function test_the_publish_tag_survives_the_opt_out(): void
    {
        $paths = ServiceProvider::pathsToPublish(BlafastServiceProvider::class, 'blafast-fundation-migrations');

        $this->assertNotEmpty($paths);
    }
}
