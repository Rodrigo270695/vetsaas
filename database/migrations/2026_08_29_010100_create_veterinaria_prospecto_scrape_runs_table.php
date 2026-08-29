<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de cada corrida del scraper de prospectos (cron diario o manual
 * desde el panel), para mostrar historial y depurar fuentes que fallan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veterinaria_prospecto_scrape_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            /** cron|manual */
            $table->string('origen', 20)->default('cron');

            $table->timestampTz('iniciado_at');
            $table->timestampTz('finalizado_at')->nullable();

            /** ok|parcial|error */
            $table->string('estado', 20)->default('ok');

            $table->unsignedSmallInteger('nuevos')->default(0);
            $table->unsignedSmallInteger('duplicados')->default(0);
            $table->unsignedSmallInteger('sin_datos')->default(0);

            /** Slugs de ubicaciones visitadas en esta corrida. */
            $table->json('ubicaciones_visitadas')->default('[]');

            /** Mensajes de error por ubicación (si los hubo). */
            $table->json('errores')->default('[]');

            $table->uuid('iniciado_por_id')->nullable();

            $table->timestampsTz();

            $table->index('iniciado_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veterinaria_prospecto_scrape_runs');
    }
};
