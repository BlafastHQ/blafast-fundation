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

        // Create organizations table
        if (! $schema->hasTable('organizations')) {
            $schema->create('organizations', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create organization_user pivot table
        if (! $schema->hasTable('organization_user')) {
            $schema->create('organization_user', function ($table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignUuid('organization_id')->constrained('organizations')->onDelete('cascade');
                $table->string('role');
                $table->boolean('is_active')->default(true);
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'organization_id']);
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

        // Create permission tables
        $this->createPermissionTables();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Laravel\Sanctum\SanctumServiceProvider::class,
            \Spatie\Permission\PermissionServiceProvider::class,
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
            'model' => \Blafast\Foundation\Tests\Fixtures\User::class,
        ]);
        config()->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);

        // Configure permissions
        config()->set('permission.teams', true);
        config()->set('permission.column_names.team_foreign_key', 'organization_id');
        config()->set('permission.column_names.model_morph_key', 'model_uuid');
        config()->set('permission.models.permission', \Blafast\Foundation\Models\Permission::class);
        config()->set('permission.models.role', \Blafast\Foundation\Models\Role::class);
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

        // Create organizations table
        if (! $this->app['db']->connection()->getSchemaBuilder()->hasTable('organizations')) {
            $this->app['db']->connection()->getSchemaBuilder()->create('organizations', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create organization_user pivot table
        if (! $this->app['db']->connection()->getSchemaBuilder()->hasTable('organization_user')) {
            $this->app['db']->connection()->getSchemaBuilder()->create('organization_user', function ($table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignUuid('organization_id')->constrained('organizations')->onDelete('cascade');
                $table->string('role');
                $table->boolean('is_active')->default(true);
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'organization_id']);
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

        // Create permission tables for Spatie Permission
        $this->createPermissionTables();
    }

    protected function createPermissionTables(): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        // Create permissions table
        if (! $schema->hasTable('permissions')) {
            $schema->create('permissions', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('guard_name');
                $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
                $table->timestamps();
                $table->unique(['organization_id', 'name', 'guard_name']);
            });
        }

        // Create roles table
        if (! $schema->hasTable('roles')) {
            $schema->create('roles', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('guard_name');
                $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
                $table->timestamps();
                $table->unique(['organization_id', 'name', 'guard_name']);
            });
        }

        // Create model_has_permissions table
        if (! $schema->hasTable('model_has_permissions')) {
            $schema->create('model_has_permissions', function ($table) {
                $table->uuid('permission_id');
                $table->string('model_type');
                $table->uuid('model_uuid');
                $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
                $table->primary(['organization_id', 'permission_id', 'model_uuid', 'model_type'], 'model_has_permissions_permission_model_type_primary');
                $table->index(['model_uuid', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            });
        }

        // Create model_has_roles table
        if (! $schema->hasTable('model_has_roles')) {
            $schema->create('model_has_roles', function ($table) {
                $table->uuid('role_id');
                $table->string('model_type');
                $table->uuid('model_uuid');
                $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                $table->primary(['organization_id', 'role_id', 'model_uuid', 'model_type'], 'model_has_roles_role_model_type_primary');
                $table->index(['model_uuid', 'model_type'], 'model_has_roles_model_id_model_type_index');
            });
        }

        // Create role_has_permissions table
        if (! $schema->hasTable('role_has_permissions')) {
            $schema->create('role_has_permissions', function ($table) {
                $table->uuid('permission_id');
                $table->uuid('role_id');
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
            });
        }
    }
}
