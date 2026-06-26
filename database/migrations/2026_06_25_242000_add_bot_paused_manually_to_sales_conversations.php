<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue pausa manual (panel/CLI) de pausa automática del sistema.
 * Pausa manual → el bot no se reactiva solo hasta que Rodrigo pulse Reanudar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            $table->boolean('bot_paused_manually')->default(false)->after('bot_active');
        });
    }

    public function down(): void
    {
        Schema::table('sales_conversations', function (Blueprint $table): void {
            $table->dropColumn('bot_paused_manually');
        });
    }
};
