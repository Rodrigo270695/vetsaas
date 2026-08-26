<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula la aplicación registrada con la cita de la próxima visita (paquete + fecha/hora).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacunas_aplicadas', function (Blueprint $table) {
            $table->foreignUuid('cita_proxima_id')
                ->nullable()
                ->after('fecha_proxima_sugerida')
                ->constrained('citas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vacunas_aplicadas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cita_proxima_id');
        });
    }
};
