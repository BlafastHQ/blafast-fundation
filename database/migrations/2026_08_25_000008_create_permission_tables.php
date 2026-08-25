<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            throw new Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }
        if ($teams && empty($columnNames['team_foreign_key'] ?? null)) {
            throw new Exception('Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        // A host that already carries the VENDOR spatie permission tables (bigint ids,
        // no organization_id team column) must neither be collided with NOR silently
        // skipped — skipping would leave every permission query broken at runtime.
        // Abort with the required action instead. Package-shaped tables (a forked /
        // republished install) are skipped: `migrate` twice must be a no-op.
        if ($teams && Schema::hasTable($tableNames['permissions'])
            && ! Schema::hasColumn($tableNames['permissions'], $columnNames['team_foreign_key'])) {
            throw new RuntimeException(
                "Table [{$tableNames['permissions']}] exists without the [{$columnNames['team_foreign_key']}] team column — "
                .'these are the vendor spatie permission tables, incompatible with the blafast-fundation schema '
                .'(uuid keys, organization team column). Migrate or drop them first — see docs/HOST-REQUIREMENTS.md.'
            );
        }

        // Create permissions table
        Schema::hasTable($tableNames['permissions']) || Schema::create($tableNames['permissions'], function (Blueprint $table) use ($teams, $columnNames) {
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
        Schema::hasTable($tableNames['roles']) || Schema::create($tableNames['roles'], function (Blueprint $table) use ($teams, $columnNames) {
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
        if (! Schema::hasTable($tableNames['model_has_permissions'])) {
            Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames, $teams, $permissionRegistrar) {
                $table->uuid($permissionRegistrar->pivotPermission);

                $table->string('model_type');
                $table->uuid($columnNames['model_morph_key']);
                $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

                $table->foreign($permissionRegistrar->pivotPermission)
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');

                if ($teams) {
                    $table->foreignUuid($columnNames['team_foreign_key'])
                        ->nullable()
                        ->constrained('organizations')
                        ->onDelete('cascade');
                    $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');
                } else {
                    $table->primary(
                        [$permissionRegistrar->pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                        'model_has_permissions_permission_model_type_primary'
                    );
                }
            });

            if ($teams) {
                // No composite PRIMARY KEY here: Postgres forces PK columns NOT NULL,
                // while the global Superadmin grant needs a NULL team column (C6).
                // A pair of partial unique indexes (valid on pgsql AND sqlite; MySQL is
                // not supported — see docs/HOST-REQUIREMENTS.md) enforces uniqueness
                // for both org-scoped and global rows, and still allows the same
                // permission to be granted to the same model in several organizations.
                DB::statement(sprintf(
                    'CREATE UNIQUE INDEX model_has_permissions_org_unique ON %s (%s, %s, %s, model_type) WHERE %s IS NOT NULL',
                    $tableNames['model_has_permissions'],
                    $columnNames['team_foreign_key'],
                    $permissionRegistrar->pivotPermission,
                    $columnNames['model_morph_key'],
                    $columnNames['team_foreign_key'],
                ));
                DB::statement(sprintf(
                    'CREATE UNIQUE INDEX model_has_permissions_global_unique ON %s (%s, %s, model_type) WHERE %s IS NULL',
                    $tableNames['model_has_permissions'],
                    $permissionRegistrar->pivotPermission,
                    $columnNames['model_morph_key'],
                    $columnNames['team_foreign_key'],
                ));
            }
        }

        // Create model_has_roles pivot table
        if (! Schema::hasTable($tableNames['model_has_roles'])) {
            Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames, $teams, $permissionRegistrar) {
                $table->uuid($permissionRegistrar->pivotRole);

                $table->string('model_type');
                $table->uuid($columnNames['model_morph_key']);
                $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

                $table->foreign($permissionRegistrar->pivotRole)
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');

                if ($teams) {
                    $table->foreignUuid($columnNames['team_foreign_key'])
                        ->nullable()
                        ->constrained('organizations')
                        ->onDelete('cascade');
                    $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');
                } else {
                    $table->primary(
                        [$permissionRegistrar->pivotRole, $columnNames['model_morph_key'], 'model_type'],
                        'model_has_roles_role_model_type_primary'
                    );
                }
            });

            if ($teams) {
                // Same rationale as model_has_permissions above (C6).
                DB::statement(sprintf(
                    'CREATE UNIQUE INDEX model_has_roles_org_unique ON %s (%s, %s, %s, model_type) WHERE %s IS NOT NULL',
                    $tableNames['model_has_roles'],
                    $columnNames['team_foreign_key'],
                    $permissionRegistrar->pivotRole,
                    $columnNames['model_morph_key'],
                    $columnNames['team_foreign_key'],
                ));
                DB::statement(sprintf(
                    'CREATE UNIQUE INDEX model_has_roles_global_unique ON %s (%s, %s, model_type) WHERE %s IS NULL',
                    $tableNames['model_has_roles'],
                    $permissionRegistrar->pivotRole,
                    $columnNames['model_morph_key'],
                    $columnNames['team_foreign_key'],
                ));
            }
        }

        // Create role_has_permissions pivot table
        Schema::hasTable($tableNames['role_has_permissions']) || Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames, $permissionRegistrar) {
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
            throw new Exception('Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');
        }

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
