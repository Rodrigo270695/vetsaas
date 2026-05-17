<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Distritos / municipios (hoja del catálogo geográfico).
 *
 * Cuarto nivel:
 *   paises → departamentos → provincias → [distritos]
 *
 * Este es el nivel que se referencia desde `sedes`, `tenants`,
 * `owners`, etc. Para búsquedas rápidas tipo autocomplete
 * ("escribe Linc..." → "Lince — Lima, Lima") se agrega un índice
 * trigram (pg_trgm) sobre `name`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provincia_id')
                ->constrained('provincias')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('name', 100);
            $table->boolean('status')->default(true);
            $table->timestampsTz();

            $table->index('status');
            $table->index('name');
            $table->index(['provincia_id', 'status']);
        });

        // Índice trigram para búsqueda parcial (autocomplete) en
        // PostgreSQL. Aprovecha la extensión `pg_trgm` ya habilitada
        // por la migración de sedes.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement(
                'CREATE INDEX IF NOT EXISTS idx_distritos_name_trgm '
                .'ON distritos USING GIN (name gin_trgm_ops)',
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_distritos_name_trgm');
        }

        Schema::dropIfExists('distritos');
    }
};
