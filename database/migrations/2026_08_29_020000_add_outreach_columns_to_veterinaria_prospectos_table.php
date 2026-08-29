<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas para el outreach de IA/WhatsApp hacia prospectos veterinarios:
 * marca cuándo se le mandó el primer mensaje, quién lo disparó (null =
 * automático/cron) y a qué `sales_conversation` quedó enlazado, para que
 * las respuestas entrantes las siga manejando el chatbot IA existente
 * (misma tabla que usa `plataforma/salesbot-conversations`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veterinaria_prospectos', function (Blueprint $table): void {
            $table->uuid('sales_conversation_id')->nullable()->after('estado');
            $table->timestampTz('mensaje_enviado_at')->nullable()->after('sales_conversation_id');
            $table->uuid('mensaje_enviado_por_id')->nullable()->after('mensaje_enviado_at');
            $table->unsignedSmallInteger('mensaje_intentos')->default(0)->after('mensaje_enviado_por_id');
            $table->string('mensaje_error', 300)->nullable()->after('mensaje_intentos');

            $table->index('sales_conversation_id');
            $table->index('mensaje_enviado_at');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // `sales_conversations` puede no existir aún en entornos con
            // migraciones desfasadas; la FK solo se agrega si ya existe
            // (en un entorno correctamente migrado, siempre existirá).
            if (Schema::hasTable('sales_conversations')) {
                DB::statement('ALTER TABLE veterinaria_prospectos ADD CONSTRAINT veterinaria_prospectos_sales_conversation_fk FOREIGN KEY (sales_conversation_id) REFERENCES sales_conversations (id) ON DELETE SET NULL');
            }

            if (Schema::hasTable('users')) {
                DB::statement('ALTER TABLE veterinaria_prospectos ADD CONSTRAINT veterinaria_prospectos_mensaje_enviado_por_fk FOREIGN KEY (mensaje_enviado_por_id) REFERENCES users (id) ON DELETE SET NULL');
            }
        }
    }

    public function down(): void
    {
        Schema::table('veterinaria_prospectos', function (Blueprint $table): void {
            $table->dropColumn([
                'sales_conversation_id',
                'mensaje_enviado_at',
                'mensaje_enviado_por_id',
                'mensaje_intentos',
                'mensaje_error',
            ]);
        });
    }
};
