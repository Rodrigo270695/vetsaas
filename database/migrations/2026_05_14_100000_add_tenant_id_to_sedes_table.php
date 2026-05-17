<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
        });

        Schema::table('sedes', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')
                ->after('id')
                ->nullable()
                ->constrained('tenants')
                ->cascadeOnDelete();
        });

        // Semilla histórica incorrecta: sedes globales sin clínica.
        DB::table('sedes')->whereNull('tenant_id')->delete();

        DB::statement('ALTER TABLE sedes ALTER COLUMN tenant_id SET NOT NULL');

        Schema::table('sedes', function (Blueprint $table) {
            $table->unique(['tenant_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'codigo']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('sedes', function (Blueprint $table) {
            $table->unique('codigo');
        });
    }
};
