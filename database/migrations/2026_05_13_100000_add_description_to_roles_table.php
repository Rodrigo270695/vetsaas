<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna `description` a la tabla `roles` de Spatie.
 *
 * Spatie no incluye descripción por defecto, pero la necesitamos en el
 * panel para que el admin entienda el propósito de cada rol custom
 * ("Veterinario senior con acceso a quirófano", etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->string('description', 255)
                ->nullable()
                ->after('guard_name');
        });
    }

    public function down(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn('description');
        });
    }
};
