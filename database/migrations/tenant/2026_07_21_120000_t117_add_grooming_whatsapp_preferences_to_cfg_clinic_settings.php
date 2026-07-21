<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    /** @var list<string> */
    private const COLUMNS = [
        'notificar_grooming_creado_whatsapp_activo',
        'notificar_grooming_en_proceso_whatsapp_activo',
        'notificar_grooming_completado_whatsapp_activo',
        'notificar_grooming_cancelado_whatsapp_activo',
        'notificar_grooming_no_asistio_whatsapp_activo',
    ];

    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('cfg_clinic_settings')) {
                return;
            }

            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('cfg_clinic_settings', $column)) {
                    continue;
                }

                Schema::table('cfg_clinic_settings', function (Blueprint $table) use ($column): void {
                    $table->boolean($column)->default(true);
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

            $columns = array_values(array_filter(
                self::COLUMNS,
                fn (string $column): bool => Schema::hasColumn('cfg_clinic_settings', $column),
            ));

            if ($columns !== []) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        });
    }
};
