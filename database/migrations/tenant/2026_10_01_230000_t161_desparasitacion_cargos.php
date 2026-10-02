<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Precuenta de una ficha de desparasitación.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('consulta_cargos') || ! Schema::hasTable('desparasitaciones')) {
                return;
            }

            if (! Schema::hasColumn('consulta_cargos', 'desparasitacion_id')) {
                Schema::table('consulta_cargos', function (Blueprint $table): void {
                    $table->foreignUuid('desparasitacion_id')
                        ->nullable()
                        ->constrained('desparasitaciones')
                        ->nullOnDelete();
                });
            }

            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS consulta_cargos_desparasitacion_pendiente_unique ON consulta_cargos (desparasitacion_id) WHERE desparasitacion_id IS NOT NULL AND venta_id IS NULL');
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('consulta_cargos') || ! Schema::hasColumn('consulta_cargos', 'desparasitacion_id')) {
                return;
            }

            DB::statement('DROP INDEX IF EXISTS consulta_cargos_desparasitacion_pendiente_unique');
            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('desparasitacion_id');
            });
        });
    }
};
