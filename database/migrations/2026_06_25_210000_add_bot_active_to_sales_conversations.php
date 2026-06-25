<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega control de activación del bot por conversación.
 *
 * bot_active = true  → el bot responde automáticamente
 * bot_active = false → el bot se calla (Rodrigo está escribiendo manualmente)
 *
 * Cómo pausar el bot para una conversación específica:
 *   UPDATE sales_conversations SET bot_active = false WHERE phone = '51987654321';
 *
 * O desde tinker:
 *   SalesConversation::where('phone','51987654321')->update(['bot_active'=>false]);
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            // El bot solo responde si bot_active = true.
            $table->boolean('bot_active')->default(true)->after('turn_count');

            // Razón por la que se activó el bot (para analytics).
            // Ej: "trigger:vetsaas", "trigger:demo", "trigger:precio"
            $table->string('activation_trigger', 100)->nullable()->after('bot_active');
        });
    }

    public function down(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            $table->dropColumn(['bot_active', 'activation_trigger']);
        });
    }
};
