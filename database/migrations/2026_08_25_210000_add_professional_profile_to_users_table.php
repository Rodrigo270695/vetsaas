<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha profesional opcional para personal clínico (p. ej. veterinario):
 * documento de identidad, colegiatura y archivos (CV, DNI escaneado, firma).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'documento_tipo')) {
                $table->string('documento_tipo', 10)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'documento_numero')) {
                $table->string('documento_numero', 32)->nullable()->after('documento_tipo');
            }
            if (! Schema::hasColumn('users', 'colegiatura')) {
                $table->string('colegiatura', 40)->nullable()->after('documento_numero');
            }
            if (! Schema::hasColumn('users', 'cv_path')) {
                $table->string('cv_path', 500)->nullable()->after('colegiatura');
            }
            if (! Schema::hasColumn('users', 'dni_file_path')) {
                $table->string('dni_file_path', 500)->nullable()->after('cv_path');
            }
            if (! Schema::hasColumn('users', 'firma_path')) {
                $table->string('firma_path', 500)->nullable()->after('dni_file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['documento_tipo', 'documento_numero', 'colegiatura', 'cv_path', 'dni_file_path', 'firma_path'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
