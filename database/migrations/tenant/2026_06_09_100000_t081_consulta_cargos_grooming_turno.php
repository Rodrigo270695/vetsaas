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
                $table->foreignUuid('grooming_turno_id')
                    ->nullable()
                    ->after('internamiento_id')
                    ->constrained('grooming_turnos')
                    ->nullOnDelete();
                $table->unique('grooming_turno_id');
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

    public function down(): void
    {
        $this->runInTenant(function (): void {
            DB::statement('ALTER TABLE consulta_cargos DROP CONSTRAINT IF EXISTS consulta_cargos_origen_check');

            DB::statement('DELETE FROM consulta_cargos WHERE grooming_turno_id IS NOT NULL');

            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->dropForeign(['grooming_turno_id']);
                $table->dropUnique(['grooming_turno_id']);
                $table->dropColumn('grooming_turno_id');
            });

            DB::statement("
                ALTER TABLE consulta_cargos
                ADD CONSTRAINT consulta_cargos_origen_check
                CHECK (
                    (consulta_id IS NOT NULL AND internamiento_id IS NULL)
                    OR (consulta_id IS NULL AND internamiento_id IS NOT NULL)
                )
            ");
        });
    }
};
