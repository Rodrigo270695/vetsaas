<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de “pedir refresco GPS” (cron 2×/día). El navegador del tenant
 * captura y guarda lat/lng; el servidor solo agenda el refresco.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'geo_consent_at')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenants', 'geo_refresh_requested_at')) {
                $table->timestampTz('geo_refresh_requested_at')->nullable()->after('geo_captured_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tenants', 'geo_refresh_requested_at')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('geo_refresh_requested_at');
        });
    }
};
