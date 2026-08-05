<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite varias pre-cuentas históricas por origen (consulta/grooming/hotel/internamiento).
 * Solo una pendiente (venta_id null) se usa en la UI; las cobradas quedan con venta_id.
 *
 * Nota: consulta_id ya perdió su UNIQUE en t078; los demás orígenes sí lo tienen.
 * Todo el up es idempotente (IF EXISTS / IF NOT EXISTS) por tenants heterogéneos.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            // Unique constraints (pueden faltar en algunos schemas).
            foreach ([
                'consulta_cargos_consulta_id_unique',
                'consulta_cargos_internamiento_id_unique',
                'consulta_cargos_grooming_turno_id_unique',
                'consulta_cargos_hotel_estancia_id_unique',
            ] as $constraint) {
                DB::statement("ALTER TABLE consulta_cargos DROP CONSTRAINT IF EXISTS {$constraint}");
                // Por si quedó como índice suelto y no como constraint.
                DB::statement("DROP INDEX IF EXISTS {$constraint}");
            }

            $this->ensureIndex('consulta_cargos_consulta_id_venta_id_index', '(consulta_id, venta_id)');
            $this->ensureIndex('consulta_cargos_internamiento_id_venta_id_index', '(internamiento_id, venta_id)');
            $this->ensureIndex('consulta_cargos_grooming_turno_id_venta_id_index', '(grooming_turno_id, venta_id)');
            $this->ensureIndex('consulta_cargos_hotel_estancia_id_venta_id_index', '(hotel_estancia_id, venta_id)');

            // A lo sumo una precuenta pendiente por origen.
            $this->ensureUniqueIndex(
                'consulta_cargos_consulta_pendiente_unique',
                '(consulta_id)',
                'consulta_id IS NOT NULL AND venta_id IS NULL',
            );
            $this->ensureUniqueIndex(
                'consulta_cargos_internamiento_pendiente_unique',
                '(internamiento_id)',
                'internamiento_id IS NOT NULL AND venta_id IS NULL',
            );
            $this->ensureUniqueIndex(
                'consulta_cargos_grooming_pendiente_unique',
                '(grooming_turno_id)',
                'grooming_turno_id IS NOT NULL AND venta_id IS NULL',
            );
            $this->ensureUniqueIndex(
                'consulta_cargos_hotel_pendiente_unique',
                '(hotel_estancia_id)',
                'hotel_estancia_id IS NOT NULL AND venta_id IS NULL',
            );
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            DB::statement('DROP INDEX IF EXISTS consulta_cargos_consulta_pendiente_unique');
            DB::statement('DROP INDEX IF EXISTS consulta_cargos_internamiento_pendiente_unique');
            DB::statement('DROP INDEX IF EXISTS consulta_cargos_grooming_pendiente_unique');
            DB::statement('DROP INDEX IF EXISTS consulta_cargos_hotel_pendiente_unique');

            DB::statement('DROP INDEX IF EXISTS consulta_cargos_consulta_id_venta_id_index');
            DB::statement('DROP INDEX IF EXISTS consulta_cargos_internamiento_id_venta_id_index');
            DB::statement('DROP INDEX IF EXISTS consulta_cargos_grooming_turno_id_venta_id_index');
            DB::statement('DROP INDEX IF EXISTS consulta_cargos_hotel_estancia_id_venta_id_index');

            Schema::table('consulta_cargos', function (Blueprint $table): void {
                // Reponer unique solo es seguro si hay a lo sumo un cargo por origen.
                $table->unique('internamiento_id');
                $table->unique('grooming_turno_id');
                $table->unique('hotel_estancia_id');
            });
        });
    }

    private function ensureIndex(string $name, string $columnsSql): void
    {
        if ($this->indexExists($name)) {
            return;
        }

        DB::statement("CREATE INDEX {$name} ON consulta_cargos {$columnsSql}");
    }

    private function ensureUniqueIndex(string $name, string $columnsSql, string $whereSql): void
    {
        if ($this->indexExists($name)) {
            return;
        }

        DB::statement("CREATE UNIQUE INDEX {$name} ON consulta_cargos {$columnsSql} WHERE {$whereSql}");
    }

    private function indexExists(string $indexName): bool
    {
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?',
            [$indexName],
        );

        return $row !== null;
    }
};
