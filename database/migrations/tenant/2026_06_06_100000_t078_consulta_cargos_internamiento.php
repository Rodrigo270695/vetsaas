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
            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->dropForeign(['consulta_id']);
            });

            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->dropUnique(['consulta_id']);
            });

            DB::statement('ALTER TABLE consulta_cargos ALTER COLUMN consulta_id DROP NOT NULL');

            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->foreign('consulta_id')
                    ->references('id')
                    ->on('consultas')
                    ->nullOnDelete();

                $table->foreignUuid('internamiento_id')
                    ->nullable()
                    ->after('consulta_id')
                    ->constrained('internamientos')
                    ->nullOnDelete();

                $table->unique('internamiento_id');
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

    public function down(): void
    {
        $this->runInTenant(function (): void {
            DB::statement('ALTER TABLE consulta_cargos DROP CONSTRAINT IF EXISTS consulta_cargos_origen_check');

            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->dropForeign(['internamiento_id']);
                $table->dropUnique(['internamiento_id']);
                $table->dropColumn('internamiento_id');
            });

            DB::statement('DELETE FROM consulta_cargos WHERE consulta_id IS NULL');

            DB::statement('ALTER TABLE consulta_cargos ALTER COLUMN consulta_id SET NOT NULL');

            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->dropForeign(['consulta_id']);
            });

            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->unique('consulta_id');
                $table->foreign('consulta_id')
                    ->references('id')
                    ->on('consultas')
                    ->cascadeOnDelete();
            });
        });
    }
};
