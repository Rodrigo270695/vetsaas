<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activa la extensión `pg_trgm` y agrega índices GIN trigram sobre las
 * columnas que más se filtran con `ILIKE %...%` (búsqueda parcial).
 *
 * Sin estos índices, `WHERE nombre ILIKE '%lima%'` hace seq scan y se
 * vuelve lento a partir de unas pocas miles de filas. Con índices GIN
 * sobre `gin_trgm_ops` Postgres puede usar el índice incluso con `%...%`.
 *
 * Solo aplica a Postgres (otras DBs no soportan pg_trgm).
 */
return new class extends Migration
{
    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    public function up(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // Índices GIN sobre las columnas usadas en `where ILIKE` del controller.
        // `gin_trgm_ops` permite que Postgres elija el índice para LIKE/ILIKE
        // con comodines a ambos lados.
        Schema::table('sedes', function () {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS sedes_nombre_trgm_idx '
                .'ON sedes USING gin (nombre gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS sedes_codigo_trgm_idx '
                .'ON sedes USING gin (codigo gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS sedes_direccion_trgm_idx '
                .'ON sedes USING gin (direccion gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS sedes_distrito_trgm_idx '
                .'ON sedes USING gin (distrito gin_trgm_ops)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS sedes_email_trgm_idx '
                .'ON sedes USING gin (email gin_trgm_ops)'
            );
        });
    }

    public function down(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS sedes_nombre_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS sedes_codigo_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS sedes_direccion_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS sedes_distrito_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS sedes_email_trgm_idx');
        // Dejamos `pg_trgm` activa: otros módulos pueden depender de ella.
    }
};
