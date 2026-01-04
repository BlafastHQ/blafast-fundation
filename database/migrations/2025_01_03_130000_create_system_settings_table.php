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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('type')->default('string'); // string, integer, boolean, float, json, array
            $table->string('group')->nullable(); // For organizing settings (general, api, notifications, etc.)
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // Can be read without authentication
            $table->timestamps();

            $table->index('group');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
