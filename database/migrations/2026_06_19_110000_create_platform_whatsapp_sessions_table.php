<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_whatsapp_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('openwa_session_id', 80);
            $table->string('openwa_session_name', 120)->unique();
            $table->string('status', 32)->default('created');
            $table->string('phone', 30)->nullable();
            $table->string('push_name', 120)->nullable();
            $table->timestampTz('connected_at')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE platform_whatsapp_sessions ADD CONSTRAINT platform_whatsapp_sessions_status_chk CHECK (status IN ('created','initializing','qr_ready','authenticating','ready','disconnected','failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_whatsapp_sessions');
    }
};
