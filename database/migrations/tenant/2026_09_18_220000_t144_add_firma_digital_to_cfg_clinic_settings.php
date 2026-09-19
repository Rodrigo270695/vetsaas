<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firma digital de la clínica para PDF clínicos (imagen + nombre + colegiatura
 * y lista de documentos donde se imprime).
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('cfg_clinic_settings')) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                if (! Schema::hasColumn('cfg_clinic_settings', 'firma_digital_path')) {
                    $table->string('firma_digital_path', 255)->nullable();
                }
                if (! Schema::hasColumn('cfg_clinic_settings', 'firma_digital_nombre')) {
                    $table->string('firma_digital_nombre', 180)->nullable();
                }
                if (! Schema::hasColumn('cfg_clinic_settings', 'firma_digital_colegiatura')) {
                    $table->string('firma_digital_colegiatura', 40)->nullable();
                }
                if (! Schema::hasColumn('cfg_clinic_settings', 'firma_digital_documentos')) {
                    $table->json('firma_digital_documentos')->nullable();
                }
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('cfg_clinic_settings')) {
                return;
            }

            foreach (['firma_digital_path', 'firma_digital_nombre', 'firma_digital_colegiatura', 'firma_digital_documentos'] as $col) {
                if (! Schema::hasColumn('cfg_clinic_settings', $col)) {
                    continue;
                }
                Schema::table('cfg_clinic_settings', function (Blueprint $table) use ($col): void {
                    $table->dropColumn($col);
                });
            }
        });
    }
};
