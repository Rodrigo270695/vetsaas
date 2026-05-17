<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 30)->unique();
            $table->string('descripcion', 200)->nullable();
            $table->string('tipo_descuento', 15);
            $table->decimal('valor', 10, 2);
            $table->foreignUuid('plan_id_restriccion')->nullable()->constrained('plans');
            $table->integer('max_usos')->nullable();
            $table->integer('usos_actuales')->default(0);
            $table->boolean('un_uso_por_tenant')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestampTz('valido_desde')->nullable();
            $table->timestampTz('valido_hasta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
