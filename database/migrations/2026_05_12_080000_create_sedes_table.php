<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre', 150);
            $table->string('codigo', 10)->unique();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('distrito', 120)->nullable();
            $table->string('provincia', 120)->nullable();
            $table->string('departamento', 120)->nullable();
            $table->string('serie_factura', 4)->nullable();
            $table->string('serie_boleta', 4)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('activa');
            $table->index('nombre');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sedes ADD CONSTRAINT chk_sedes_codigo_format CHECK (codigo ~ '^[A-Z0-9\\-]+$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sedes');
    }
};
