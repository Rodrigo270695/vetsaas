<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla en `public` (no-tenant): prospectos de venta (clínicas/hospitales
 * veterinarios) capturados desde directorios públicos o registrados a mano,
 * para prospección comercial de VetSaaS. No tiene relación con `Tenant`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veterinaria_prospectos', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('nombre', 200);
            $table->string('tipo', 20)->default('clinica'); // clinica|hospital

            /** Dígitos normalizados (sin +/espacios), null si no se pudo capturar. */
            $table->string('telefono_normalizado', 20)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('correo', 190)->nullable();

            $table->string('direccion', 300)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('distrito', 100)->nullable();

            $table->string('horario', 200)->nullable();
            $table->boolean('es_24_horas')->default(false);

            /** Sitio/URL de donde se extrajo el registro (o null si es manual). */
            $table->string('fuente_sitio', 100)->nullable();
            $table->string('fuente_url', 500)->nullable();
            $table->string('ubicacion_slug', 100)->nullable();

            /** manual|scraping_auto */
            $table->string('origen', 20)->default('scraping_auto');

            /** nuevo|contactado|conversando|demo_agendada|cliente|no_interesado */
            $table->string('estado', 20)->default('nuevo');

            /** Fecha en la que se capturó/registró (puede diferir de created_at si se re-importa). */
            $table->timestampTz('capturado_at');

            $table->uuid('creado_por_id')->nullable();

            $table->timestampsTz();

            $table->index('telefono_normalizado');
            $table->index(['departamento', 'distrito']);
            $table->index('estado');
            $table->index('ubicacion_slug');
            $table->index('capturado_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veterinaria_prospectos');
    }
};
