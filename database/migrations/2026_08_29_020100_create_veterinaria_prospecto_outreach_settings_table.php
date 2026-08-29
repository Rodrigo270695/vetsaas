<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración (singleton, `public`) del envío automático de WhatsApp/IA
 * hacia prospectos veterinarios: si está activo, cuántos mensajes manda
 * por corrida y a qué hora del día se dispara.
 *
 * El tiempo de espera ENTRE cada mensaje (para no parecer spam y evitar
 * bloqueos de WhatsApp) no es configurable aquí a propósito: lo decide
 * `VeterinariaProspectoOutreachService` con un jitter aleatorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veterinaria_prospecto_outreach_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->boolean('automatico_activo')->default(false);

            /** Máximo de mensajes de primer contacto por corrida (recomendado 5-10/día). */
            $table->unsignedSmallInteger('mensajes_por_corrida')->default(8);

            /** Hora local (America/Lima) HH:MM en la que debe lanzarse la corrida diaria. */
            $table->string('hora_envio', 5)->default('10:00');

            $table->timestampTz('ultima_corrida_at')->nullable();
            $table->uuid('actualizado_por_id')->nullable();

            $table->timestampsTz();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE veterinaria_prospecto_outreach_settings ADD CONSTRAINT veterinaria_prospecto_outreach_settings_updated_by_fk FOREIGN KEY (actualizado_por_id) REFERENCES users (id) ON DELETE SET NULL');
            DB::statement('CREATE UNIQUE INDEX uq_veterinaria_prospecto_outreach_settings_single_row ON veterinaria_prospecto_outreach_settings ((TRUE))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('veterinaria_prospecto_outreach_settings');
    }
};
