<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separa motivo de consulta (título HC) de notas libres.
 * Antes `motivo` se usaba como «anotaciones adicionales».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consultas')) {
            return;
        }

        Schema::table('consultas', function (Blueprint $table): void {
            if (! Schema::hasColumn('consultas', 'anotaciones')) {
                $table->text('anotaciones')->nullable()->after('motivo');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('consultas') || ! Schema::hasColumn('consultas', 'anotaciones')) {
            return;
        }

        Schema::table('consultas', function (Blueprint $table): void {
            $table->dropColumn('anotaciones');
        });
    }
};
