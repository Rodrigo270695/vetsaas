<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('bot_ia_activo')->default(false)->after('metodo_pago_token');
            $table->decimal('bot_ia_precio_mensual', 10, 2)->nullable()->after('bot_ia_activo');
            $table->timestampTz('bot_ia_activado_at')->nullable()->after('bot_ia_precio_mensual');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['bot_ia_activo', 'bot_ia_precio_mensual', 'bot_ia_activado_at']);
        });
    }
};
