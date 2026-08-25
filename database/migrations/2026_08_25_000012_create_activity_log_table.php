<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('activitylog.database_connection'));
        $tableName = config('activitylog.table_name');

        // A pre-existing vendor spatie-activitylog table (bigint id + morphs, no
        // organization_id) is incompatible with this schema (uuid id + uuid morphs) —
        // skipping it silently would break every activity query at runtime (H22).
        if ($schema->hasTable($tableName)) {
            if (! $schema->hasColumn($tableName, 'organization_id')) {
                throw new RuntimeException(
                    "Table [{$tableName}] exists without the [organization_id] column — this is the vendor "
                    .'spatie-activitylog schema, incompatible with blafast-fundation (uuid keys, '
                    .'organization scope). Migrate or drop it first — see docs/HOST-REQUIREMENTS.md.'
                );
            }

            return; // package-shaped table already present
        }

        $schema->create($tableName, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableUuidMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableUuidMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->uuid('organization_id')->nullable();
            $table->timestamps();
            $table->index('log_name');
            $table->index('organization_id');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->dropIfExists(config('activitylog.table_name'));
    }
};
