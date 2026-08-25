<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Feature;

use Blafast\Foundation\BlafastServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\SanctumServiceProvider;
use LaravelJsonApi\Laravel\ServiceProvider as JsonApiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

/**
 * Task 3 (H22): running the package migrations against a database that already has
 * framework/vendor tables. Framework-identical tables (jobs) and package-shaped
 * tables are skipped so `migrate` completes; VENDOR-shaped tables that the package
 * schema is incompatible with (bigint spatie media, stock notifications) abort with
 * an actionable message instead of colliding or being silently skipped.
 *
 * Standalone (no RefreshDatabase, own sqlite :memory:) so the manual table setup
 * cannot leak into the shared RefreshDatabaseState of the main suite.
 */
class VendorTableCollisionTest extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
            PermissionServiceProvider::class,
            MediaLibraryServiceProvider::class,
            ActivitylogServiceProvider::class,
            JsonApiServiceProvider::class,
            BlafastServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    private function runPackageMigration(string $name): void
    {
        $migration = include __DIR__.'/../../database/migrations/'.$name.'.php';
        $migration->up();
    }

    public function test_framework_identical_jobs_tables_are_skipped_without_error(): void
    {
        // The Laravel 12 skeleton's own jobs table (framework schema).
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        $this->runPackageMigration('2026_08_25_000014_create_jobs_table');

        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('job_batches'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
    }

    public function test_package_shaped_notifications_table_is_skipped_idempotently(): void
    {
        $this->runPackageMigration('2026_08_25_000001_create_currencies_table');
        $this->runPackageMigration('2026_08_25_000002_create_countries_table');
        $this->runPackageMigration('2026_08_25_000003_create_addresses_table');
        $this->runPackageMigration('2026_08_25_000004_create_organizations_table');
        $this->runPackageMigration('2026_08_25_000013_create_notifications_table');

        // Second run: table exists in package shape (organization_id present) — no-op.
        $this->runPackageMigration('2026_08_25_000013_create_notifications_table');

        $this->assertTrue(Schema::hasColumn('notifications', 'organization_id'));
    }

    public function test_vendor_shaped_media_table_aborts_with_an_actionable_message(): void
    {
        // The vendor spatie-medialibrary schema: bigint id, no organization_id.
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('disk');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HOST-REQUIREMENTS');

        $this->runPackageMigration('2026_08_25_000011_create_media_table');
    }

    public function test_vendor_shaped_permission_tables_abort_with_an_actionable_message(): void
    {
        config()->set('permission.teams', true);
        config()->set('permission.column_names.team_foreign_key', 'organization_id');

        // The vendor spatie permission schema: bigint id, no team column.
        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HOST-REQUIREMENTS');

        $this->runPackageMigration('2026_08_25_000008_create_permission_tables');
    }
}
