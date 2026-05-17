<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos de auditoría a `sedes`:
 * - `created_by_id`: usuario que creó la sede.
 * - `updated_by_id`: usuario que la modificó por última vez.
 *
 * Ambos son `nullable` para no romper registros previos creados sin auditoría
 * y se ponen a NULL si el usuario referenciado se borra (no queremos cascadear).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->foreignUuid('created_by_id')
                ->nullable()
                ->after('activa')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUuid('updated_by_id')
                ->nullable()
                ->after('created_by_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('created_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropForeign(['created_by_id']);
            $table->dropForeign(['updated_by_id']);
            $table->dropColumn(['created_by_id', 'updated_by_id']);
        });
    }
};
