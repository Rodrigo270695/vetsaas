<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo público de países.
 *
 * Forma parte del catálogo geográfico jerárquico:
 *   paises → departamentos → provincias → distritos
 *
 * Esta tabla se siembra con los datos oficiales (en el caso de Perú,
 * INEI). Es compartida entre tenants: vive en el schema `public`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paises', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('status')->default(true);
            $table->timestampsTz();

            $table->index('status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paises');
    }
};
