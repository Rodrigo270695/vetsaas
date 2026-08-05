<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite varias pre-cuentas históricas por origen (consulta/grooming/hotel/internamiento).
 * Solo una pendiente (venta_id null) se usa en la UI; las cobradas quedan con venta_id.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->dropUnique(['consulta_id']);
                $table->dropUnique(['internamiento_id']);
                $table->dropUnique(['grooming_turno_id']);
                $table->dropUnique(['hotel_estancia_id']);

                $table->index(['consulta_id', 'venta_id']);
                $table->index(['internamiento_id', 'venta_id']);
                $table->index(['grooming_turno_id', 'venta_id']);
                $table->index(['hotel_estancia_id', 'venta_id']);
            });

            // A lo sumo una precuenta pendiente por origen.
            DB::statement('CREATE UNIQUE INDEX consulta_cargos_consulta_pendiente_unique ON consulta_cargos (consulta_id) WHERE consulta_id IS NOT NULL AND venta_id IS NULL');
            DB::statement('CREATE UNIQUE INDEX consulta_cargos_internamiento_pendiente_unique ON consulta_cargos (internamiento_id) WHERE internamiento_id IS NOT NULL AND venta_id IS NULL');
            DB::statement('CREATE UNIQUE INDEX consulta_cargos_grooming_pendiente_unique ON consulta_cargos (grooming_turno_id) WHERE grooming_turno_id IS NOT NULL AND venta_id IS NULL');
            DB::statement('CREATE UNIQUE INDEX consulta_cargos_hotel_pendiente_unique ON consulta_cargos (hotel_estancia_id) WHERE hotel_estancia_id IS NOT NULL AND venta_id IS NULL');
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            DB::statement('DROP INDEX IF EXISTS consulta_cargos_consulta_pendiente_unique');
            DB::statement('DROP INDEX IF EXISTS consulta_cargos_internamiento_pendiente_unique');
            DB::statement('DROP INDEX IF EXISTS consulta_cargos_grooming_pendiente_unique');
            DB::statement('DROP INDEX IF EXISTS consulta_cargos_hotel_pendiente_unique');

            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->dropIndex(['consulta_id', 'venta_id']);
                $table->dropIndex(['internamiento_id', 'venta_id']);
                $table->dropIndex(['grooming_turno_id', 'venta_id']);
                $table->dropIndex(['hotel_estancia_id', 'venta_id']);

                $table->unique('consulta_id');
                $table->unique('internamiento_id');
                $table->unique('grooming_turno_id');
                $table->unique('hotel_estancia_id');
            });
        });
    }
};
