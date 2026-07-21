<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (
                ! Schema::hasTable('cfg_clinic_settings')
                || Schema::hasColumn('cfg_clinic_settings', 'recordatorio_cita_dias_antes_opciones')
            ) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                $table->json('recordatorio_cita_dias_antes_opciones')->default('[2]');
            });

            $enabled = (bool) (DB::table('cfg_clinic_settings')
                ->value('recordatorio_48h_activo') ?? true);

            DB::table('cfg_clinic_settings')->update([
                'recordatorio_cita_dias_antes_opciones' => json_encode($enabled ? [2] : []),
            ]);
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (
                Schema::hasTable('cfg_clinic_settings')
                && Schema::hasColumn('cfg_clinic_settings', 'recordatorio_cita_dias_antes_opciones')
            ) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->dropColumn('recordatorio_cita_dias_antes_opciones');
                });
            }
        });
    }
};
