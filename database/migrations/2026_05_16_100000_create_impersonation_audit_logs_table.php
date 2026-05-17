<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('superadmin_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('central_origin', 512)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'started_at']);
            $table->index(['superadmin_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_audit_logs');
    }
};
