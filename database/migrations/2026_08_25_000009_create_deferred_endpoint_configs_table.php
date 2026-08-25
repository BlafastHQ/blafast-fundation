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
        Schema::create('deferred_endpoint_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->string('http_method', 10);
            $table->string('endpoint_pattern');
            $table->boolean('is_active')->default(true);
            $table->boolean('force_deferred')->default(false);
            $table->string('priority')->default('default');
            $table->integer('timeout')->default(300);
            $table->integer('result_ttl')->default(3600);
            $table->timestamps();

            $table->index(['http_method', 'endpoint_pattern']);
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
        Schema::dropIfExists('deferred_endpoint_configs');
    }
};
