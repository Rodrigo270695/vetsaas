<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla en `public` (no-tenant) que guarda el historial de conversaciones
 * de WhatsApp con prospectos. El bot de ventas IA la usa para mantener
 * contexto entre mensajes de un mismo número.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            /** Número sin @c.us, normalizado: "51987654321" */
            $table->string('phone', 30)->unique();

            /** chatId de OpenWA: "51987654321@c.us" */
            $table->string('wa_chat_id', 40);

            /** Nombre push de WhatsApp (si lo envía OpenWA) */
            $table->string('prospect_name', 150)->nullable();

            /**
             * Historial de mensajes en formato OpenAI:
             * [{"role":"user","content":"..."}, {"role":"assistant","content":"..."}]
             * Máximo ~40 turnos (se trunca automáticamente en el servicio).
             */
            $table->json('messages')->default('[]');

            /** Cuántos mensajes han pasado (para analytics básicos) */
            $table->unsignedSmallInteger('turn_count')->default(0);

            $table->timestampTz('last_message_at')->nullable();
            $table->timestampsTz();

            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_conversations');
    }
};
