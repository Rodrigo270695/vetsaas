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
            DB::statement('ALTER TABLE consulta_cargos DROP CONSTRAINT IF EXISTS consulta_cargos_origen_check');

            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->foreignUuid('hotel_estancia_id')
                    ->nullable()
                    ->after('grooming_turno_id')
                    ->constrained('hotel_estancias')
                    ->nullOnDelete();
                $table->unique('hotel_estancia_id');
            });

            DB::statement("
                ALTER TABLE consulta_cargos
                ADD CONSTRAINT consulta_cargos_origen_check
                CHECK (
                    (consulta_id IS NOT NULL AND internamiento_id IS NULL AND grooming_turno_id IS NULL AND hotel_estancia_id IS NULL)
                    OR (consulta_id IS NULL AND internamiento_id IS NOT NULL AND grooming_turno_id IS NULL AND hotel_estancia_id IS NULL)
                    OR (consulta_id IS NULL AND internamiento_id IS NULL AND grooming_turno_id IS NOT NULL AND hotel_estancia_id IS NULL)
                    OR (consulta_id IS NULL AND internamiento_id IS NULL AND grooming_turno_id IS NULL AND hotel_estancia_id IS NOT NULL)
                )
            ");
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            DB::statement('ALTER TABLE consulta_cargos DROP CONSTRAINT IF EXISTS consulta_cargos_origen_check');

            DB::statement('DELETE FROM consulta_cargos WHERE hotel_estancia_id IS NOT NULL');

            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->dropForeign(['hotel_estancia_id']);
                $table->dropUnique(['hotel_estancia_id']);
                $table->dropColumn('hotel_estancia_id');
            });

            DB::statement("
                ALTER TABLE consulta_cargos
                ADD CONSTRAINT consulta_cargos_origen_check
                CHECK (
                    (consulta_id IS NOT NULL AND internamiento_id IS NULL AND grooming_turno_id IS NULL)
                    OR (consulta_id IS NULL AND internamiento_id IS NOT NULL AND grooming_turno_id IS NULL)
                    OR (consulta_id IS NULL AND internamiento_id IS NULL AND grooming_turno_id IS NOT NULL)
                )
            ");
        });
    }
};
