<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teams = config('permission.teams');
        $permissionRegistrar = app(PermissionRegistrar::class);

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }
        if ($teams && empty($columnNames['team_foreign_key'] ?? null)) {
            throw new \Exception('Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        // Create permissions table
        Schema::create($tableNames['permissions'], function (Blueprint $table) use ($teams, $columnNames) {
            $table->uuid('id')->primary(); // Using UUID for permission id
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            // Team foreign key
            if ($teams) {
                $table->foreignUuid($columnNames['team_foreign_key'])
                    ->nullable()
                    ->constrained('organizations')
                    ->onDelete('cascade');
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        // Create roles table
        Schema::create($tableNames['roles'], function (Blueprint $table) use ($teams, $columnNames) {
            $table->uuid('id')->primary(); // Using UUID for role id
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            // Team foreign key
            if ($teams) {
                $table->foreignUuid($columnNames['team_foreign_key'])
                    ->nullable()
                    ->constrained('organizations')
                    ->onDelete('cascade');
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        // Create model_has_permissions pivot table
        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames, $teams, $permissionRegistrar) {
            $table->uuid($permissionRegistrar->pivotPermission);

            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($permissionRegistrar->pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            // Team foreign key
            if ($teams) {
                $table->foreignUuid($columnNames['team_foreign_key'])
                    ->nullable()
                    ->constrained('organizations')
                    ->onDelete('cascade');
                $table->primary(
                    [
                        $columnNames['team_foreign_key'],
                        $permissionRegistrar->pivotPermission,
                        $columnNames['model_morph_key'],
                        'model_type',
                    ],
                    'model_has_permissions_permission_model_type_primary'
                );
            } else {
                $table->primary(
                    [$permissionRegistrar->pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
            }
        });

        // Create model_has_roles pivot table
        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames, $teams, $permissionRegistrar) {
            $table->uuid($permissionRegistrar->pivotRole);

            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($permissionRegistrar->pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            // Team foreign key
            if ($teams) {
                $table->foreignUuid($columnNames['team_foreign_key'])
                    ->nullable()
                    ->constrained('organizations')
                    ->onDelete('cascade');
                $table->primary(
                    [
                        $columnNames['team_foreign_key'],
                        $permissionRegistrar->pivotRole,
                        $columnNames['model_morph_key'],
                        'model_type',
                    ],
                    'model_has_roles_role_model_type_primary'
                );
            } else {
                $table->primary(
                    [$permissionRegistrar->pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );
            }
        });

        // Create role_has_permissions pivot table
        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames, $permissionRegistrar) {
            $table->uuid($permissionRegistrar->pivotPermission);
            $table->uuid($permissionRegistrar->pivotRole);

            $table->foreign($permissionRegistrar->pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign($permissionRegistrar->pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary([$permissionRegistrar->pivotPermission, $permissionRegistrar->pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');
        }

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
