<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('cfg_clinic_settings')) {
                return;
            }

            if (! Schema::hasColumn('cfg_clinic_settings', 'recordatorio_agenda_servicios_dias_antes_opciones')) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->json('recordatorio_agenda_servicios_dias_antes_opciones')->default('[1,2]');
                });
            }

            if (! Schema::hasColumn('cfg_clinic_settings', 'recordatorio_agenda_servicios_2h_activo')) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->boolean('recordatorio_agenda_servicios_2h_activo')->default(true);
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('cfg_clinic_settings')) {
                return;
            }

            $drop = [];
            if (Schema::hasColumn('cfg_clinic_settings', 'recordatorio_agenda_servicios_dias_antes_opciones')) {
                $drop[] = 'recordatorio_agenda_servicios_dias_antes_opciones';
            }
            if (Schema::hasColumn('cfg_clinic_settings', 'recordatorio_agenda_servicios_2h_activo')) {
                $drop[] = 'recordatorio_agenda_servicios_2h_activo';
            }
            if ($drop === []) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table) use ($drop): void {
                $table->dropColumn($drop);
            });
        });
    }
};
