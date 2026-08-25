<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Laravel's stock notifications table shares the uuid id but uses bigint
        // morphs and has no organization_id — incompatible with the package's uuid
        // notifiables. Never skip it silently (H22).
        if (Schema::hasTable('notifications')) {
            if (! Schema::hasColumn('notifications', 'organization_id')) {
                throw new RuntimeException(
                    'Table [notifications] exists without the [organization_id] column — an existing '
                    .'notifications table (e.g. Laravel\'s stock one with bigint morphs) is incompatible '
                    .'with blafast-fundation (uuid notifiable morphs, organization scope). Migrate or '
                    .'drop it first — see docs/HOST-REQUIREMENTS.md.'
                );
            }

            return; // package-shaped table already present
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->uuid('organization_id')->nullable();
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
