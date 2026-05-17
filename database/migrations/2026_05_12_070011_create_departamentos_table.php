<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departamentos / regiones de cada país.
 *
 * Segundo nivel del catálogo geográfico:
 *   paises → [departamentos] → provincias → distritos
 *
 * FK con `restrict`: el catálogo es estable; no se borran países
 * si tienen departamentos asociados (proteger integridad histórica).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pais_id')
                ->constrained('paises')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('name', 100);
            $table->boolean('status')->default(true);
            $table->timestampsTz();

            $table->index('status');
            $table->index('name');
            $table->index(['pais_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};
