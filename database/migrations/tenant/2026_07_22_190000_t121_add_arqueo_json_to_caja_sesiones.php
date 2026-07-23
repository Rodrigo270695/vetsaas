<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot inmutable del arqueo al cerrar la sesión de caja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_sesiones', function (Blueprint $table) {
            if (! Schema::hasColumn('caja_sesiones', 'arqueo_json')) {
                $table->jsonb('arqueo_json')->nullable()->after('saldo_cierre_efectivo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('caja_sesiones', function (Blueprint $table) {
            if (Schema::hasColumn('caja_sesiones', 'arqueo_json')) {
                $table->dropColumn('arqueo_json');
            }
        });
    }
};
