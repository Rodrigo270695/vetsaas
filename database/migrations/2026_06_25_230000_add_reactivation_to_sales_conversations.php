<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas para el sistema de reactivación de leads fríos.
 *
 * Un lead "frío" es aquel que tuvo actividad con el bot pero no
 * ha escrito en X días. El job diario les envía un mensaje de
 * reactivación automático y el bot retoma la conversación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            // Cuántas veces se ha enviado un mensaje de reactivación.
            $table->unsignedTinyInteger('reactivation_count')->default(0)->after('bot_active');

            // Fecha del último mensaje de reactivación enviado.
            $table->timestamp('last_reactivation_at')->nullable()->after('reactivation_count');

            // Si el lead fue convertido (registró o pagó) — para no reactivar más.
            $table->boolean('converted')->default(false)->after('last_reactivation_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            $table->dropColumn(['reactivation_count', 'last_reactivation_at', 'converted']);
        });
    }
};
