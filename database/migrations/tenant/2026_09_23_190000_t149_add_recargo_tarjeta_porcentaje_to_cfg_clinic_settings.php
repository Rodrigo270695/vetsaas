<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Porcentaje de recargo que la clínica suma al cobrar con tarjeta.
 * Se recuerda el último valor usado en el punto de venta (por defecto 5 %).
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
                if (! Schema::hasColumn('cfg_clinic_settings', 'recargo_tarjeta_porcentaje')) {
                    $table->decimal('recargo_tarjeta_porcentaje', 5, 2)->default(5);
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

            if (! Schema::hasColumn('cfg_clinic_settings', 'recargo_tarjeta_porcentaje')) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                $table->dropColumn('recargo_tarjeta_porcentaje');
            });
        });
    }
};
