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
        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('iso_alpha_2', 2)->unique();
            $table->string('iso_alpha_3', 3)->unique();
            $table->string('iso_numeric', 3)->unique();
            $table->string('phone_code', 20);
            $table->foreignUuid('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for performance
            $table->index('is_active');
            $table->index('iso_alpha_2');
            $table->index('iso_alpha_3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
