<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provincias de cada departamento.
 *
 * Tercer nivel del catálogo geográfico:
 *   paises → departamentos → [provincias] → distritos
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provincias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')
                ->constrained('departamentos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('name', 100);
            $table->boolean('status')->default(true);
            $table->timestampsTz();

            $table->index('status');
            $table->index('name');
            $table->index(['departamento_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provincias');
    }
};
