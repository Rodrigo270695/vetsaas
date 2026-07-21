<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (
                ! Schema::hasTable('cfg_clinic_settings')
                || Schema::hasColumn('cfg_clinic_settings', 'notificar_cita_whatsapp_activo')
            ) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                $table->boolean('notificar_cita_whatsapp_activo')
                    ->default(true)
                    ->after('recordatorio_2h_activo');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (
                Schema::hasTable('cfg_clinic_settings')
                && Schema::hasColumn('cfg_clinic_settings', 'notificar_cita_whatsapp_activo')
            ) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->dropColumn('notificar_cita_whatsapp_activo');
                });
            }
        });
    }
};
