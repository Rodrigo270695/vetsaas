<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_auth_session_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUuid('tenant_id')
                ->nullable()
                ->constrained('tenants')
                ->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->string('user_name');
            $table->string('user_email');
            $table->string('tenant_slug')->nullable();
            $table->string('plan_codigo', 64)->default('unknown');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('logged_in_at');
            $table->timestampTz('logged_out_at')->nullable();
            $table->string('logout_reason', 32)->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'logged_in_at']);
            $table->index(['user_id', 'logged_in_at']);
            $table->index('session_id');
            $table->index(['plan_codigo', 'logged_in_at']);
            $table->index('logged_out_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_auth_session_logs');
    }
};
