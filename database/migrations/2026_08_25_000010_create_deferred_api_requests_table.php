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
        Schema::create('deferred_api_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('user_id');
            $table->string('http_method', 10);
            $table->string('endpoint');
            // payload/headers/result carry the `encrypted:json` casts — the encrypted
            // value is an opaque base64 string that Postgres's json type rejects
            // (SQLSTATE 22P02), so these three are text, not json (C7).
            $table->text('payload')->nullable();
            $table->json('query_params')->nullable();
            $table->text('headers');
            $table->string('status')->default('pending');
            $table->integer('progress')->nullable();
            $table->string('progress_message')->nullable();
            $table->text('result')->nullable();
            $table->integer('result_status_code')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->string('priority')->default('default');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('organization_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('expires_at');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deferred_api_requests');
    }
};
