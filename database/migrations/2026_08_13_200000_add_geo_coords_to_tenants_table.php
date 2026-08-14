<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordenadas GPS del tenant (consentimiento del navegador) para el
 * mapa de calor de Reportes de plataforma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenants', 'geo_lat')) {
                $table->decimal('geo_lat', 10, 7)->nullable()->after('distrito_id');
            }
            if (! Schema::hasColumn('tenants', 'geo_lng')) {
                $table->decimal('geo_lng', 10, 7)->nullable()->after('geo_lat');
            }
            if (! Schema::hasColumn('tenants', 'geo_consent_at')) {
                $table->timestampTz('geo_consent_at')->nullable()->after('geo_lng');
            }
            if (! Schema::hasColumn('tenants', 'geo_denied_at')) {
                $table->timestampTz('geo_denied_at')->nullable()->after('geo_consent_at');
            }
            if (! Schema::hasColumn('tenants', 'geo_captured_at')) {
                $table->timestampTz('geo_captured_at')->nullable()->after('geo_denied_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            foreach (['geo_captured_at', 'geo_denied_at', 'geo_consent_at', 'geo_lng', 'geo_lat'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
