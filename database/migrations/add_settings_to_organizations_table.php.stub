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
        if (! Schema::hasColumn('organizations', 'settings')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->json('settings')->nullable()->after('contact_details');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'settings')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('settings');
            });
        }
    }
};
