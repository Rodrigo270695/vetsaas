<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_page_view_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUuid('tenant_id')
                ->nullable()
                ->constrained('tenants')
                ->nullOnDelete();
            $table->string('path', 512);
            $table->string('module', 64);
            $table->string('inertia_component', 255)->nullable();
            $table->timestampTz('seen_at');
            $table->timestampsTz();

            $table->index(['seen_at']);
            $table->index(['module', 'seen_at']);
            $table->index(['tenant_id', 'seen_at']);
            $table->index(['user_id', 'seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_page_view_logs');
    }
};
