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
                || Schema::hasColumn('cfg_clinic_settings', 'recordatorio_vacuna_dias_antes_opciones')
            ) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                $table->json('recordatorio_vacuna_dias_antes_opciones')->default('[7]');
            });

            $legacyDays = (int) (DB::table('cfg_clinic_settings')
                ->value('recordatorio_vacuna_dias_antes') ?? 7);
            $selectedDays = in_array($legacyDays, [1, 2, 3, 7, 30], true)
                ? $legacyDays
                : 7;

            DB::table('cfg_clinic_settings')->update([
                'recordatorio_vacuna_dias_antes_opciones' => json_encode([$selectedDays]),
            ]);
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (
                Schema::hasTable('cfg_clinic_settings')
                && Schema::hasColumn('cfg_clinic_settings', 'recordatorio_vacuna_dias_antes_opciones')
            ) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->dropColumn('recordatorio_vacuna_dias_antes_opciones');
                });
            }
        });
    }
};
