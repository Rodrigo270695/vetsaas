<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Afectación IGV SUNAT por clínica (gravado / exonerado / inafecto).
 *
 * Default `gravado`: tenants existentes no cambian de comportamiento.
 * Cada schema de tenant tiene su propia fila en `cfg_clinic_settings`.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (
                ! Schema::hasTable('cfg_clinic_settings')
                || Schema::hasColumn('cfg_clinic_settings', 'igv_afectacion')
            ) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                $table->string('igv_afectacion', 20)->default('gravado');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (
                Schema::hasTable('cfg_clinic_settings')
                && Schema::hasColumn('cfg_clinic_settings', 'igv_afectacion')
            ) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->dropColumn('igv_afectacion');
                });
            }
        });
    }
};
