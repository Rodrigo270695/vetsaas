<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite reconectar WhatsApp automáticamente tras caídas de OpenWA,
 * sin reabrir sesiones que el usuario desvinculó a propósito.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_whatsapp_sessions')
            && ! Schema::hasColumn('tenant_whatsapp_sessions', 'auto_reconnect')) {
            Schema::table('tenant_whatsapp_sessions', function (Blueprint $table): void {
                $table->boolean('auto_reconnect')->default(true)->after('last_error');
            });
        }

        if (Schema::hasTable('platform_whatsapp_sessions')
            && ! Schema::hasColumn('platform_whatsapp_sessions', 'auto_reconnect')) {
            Schema::table('platform_whatsapp_sessions', function (Blueprint $table): void {
                $table->boolean('auto_reconnect')->default(true)->after('last_error');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_whatsapp_sessions')
            && Schema::hasColumn('tenant_whatsapp_sessions', 'auto_reconnect')) {
            Schema::table('tenant_whatsapp_sessions', function (Blueprint $table): void {
                $table->dropColumn('auto_reconnect');
            });
        }

        if (Schema::hasTable('platform_whatsapp_sessions')
            && Schema::hasColumn('platform_whatsapp_sessions', 'auto_reconnect')) {
            Schema::table('platform_whatsapp_sessions', function (Blueprint $table): void {
                $table->dropColumn('auto_reconnect');
            });
        }
    }
};
