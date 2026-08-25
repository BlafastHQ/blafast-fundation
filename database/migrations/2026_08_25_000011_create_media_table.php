<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A pre-existing vendor spatie-medialibrary table (bigint id, no
        // organization_id) is incompatible with this schema (uuid PK, uuid morphs) —
        // skipping it silently would break every media query at runtime (H22).
        if (Schema::hasTable('media')) {
            if (! Schema::hasColumn('media', 'organization_id')) {
                throw new RuntimeException(
                    'Table [media] exists without the [organization_id] column — this is the vendor '
                    .'spatie-medialibrary schema, incompatible with blafast-fundation (uuid keys, '
                    .'organization scope). Migrate or drop it first — see docs/HOST-REQUIREMENTS.md.'
                );
            }

            return; // package-shaped table already present
        }

        Schema::create('media', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuidMorphs('model'); // This already creates an index on model_type and model_uuid
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
