<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula sedes con el catálogo geográfico oficial.
 *
 * Estrategia:
 *   - Se agrega `distrito_id` FK → distritos.id.
 *   - Se mantienen los campos `distrito`, `provincia`, `departamento`
 *     existentes como CACHE DENORMALIZADO. Al guardar la sede, el
 *     controller los rellena automáticamente desde el distrito
 *     seleccionado. Esto:
 *       (a) evita JOINs en cada listado/export (rendimiento),
 *       (b) preserva los textos históricos de sedes pre-migración.
 *
 *   - `nullOnDelete`: si algún día se borra un distrito del catálogo,
 *     la sede no se pierde — solo queda con `distrito_id = null` y los
 *     strings denormalizados intactos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->foreignId('distrito_id')
                ->nullable()
                ->after('email')
                ->constrained('distritos')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('distrito_id');
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('distrito_id');
        });
    }
};
