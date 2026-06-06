<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_whatsapp_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->string('openwa_session_id', 80);
            $table->string('openwa_session_name', 120);
            $table->string('status', 32)->default('created');
            $table->string('phone', 30)->nullable();
            $table->string('push_name', 120)->nullable();
            $table->timestampTz('connected_at')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenant_whatsapp_sessions ADD CONSTRAINT tenant_whatsapp_sessions_tenant_fk FOREIGN KEY (tenant_id) REFERENCES public.tenants (id) ON DELETE CASCADE');
            DB::statement("ALTER TABLE tenant_whatsapp_sessions ADD CONSTRAINT tenant_whatsapp_sessions_status_chk CHECK (status IN ('created','initializing','qr_ready','authenticating','ready','disconnected','failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_whatsapp_sessions');
    }
};
