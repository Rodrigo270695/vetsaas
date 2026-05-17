<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 80);
            $table->text('descripcion')->nullable();
            $table->string('badge', 50)->nullable();
            $table->string('color_hex', 7)->nullable();
            $table->decimal('precio_mensual', 10, 2)->default(0);
            $table->decimal('precio_anual', 10, 2)->nullable();
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('es_publico')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
