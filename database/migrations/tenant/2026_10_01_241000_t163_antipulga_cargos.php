<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Precuenta de una ficha de antipulgas.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('consulta_cargos') || ! Schema::hasTable('antipulgas')) {
                return;
            }

            if (! Schema::hasColumn('consulta_cargos', 'antipulga_id')) {
                Schema::table('consulta_cargos', function (Blueprint $table): void {
                    $table->foreignUuid('antipulga_id')
                        ->nullable()
                        ->constrained('antipulgas')
                        ->nullOnDelete();
                });
            }

            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS consulta_cargos_antipulga_pendiente_unique ON consulta_cargos (antipulga_id) WHERE antipulga_id IS NOT NULL AND venta_id IS NULL');
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('consulta_cargos') || ! Schema::hasColumn('consulta_cargos', 'antipulga_id')) {
                return;
            }

            DB::statement('DROP INDEX IF EXISTS consulta_cargos_antipulga_pendiente_unique');
            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('antipulga_id');
            });
        });
    }
};
