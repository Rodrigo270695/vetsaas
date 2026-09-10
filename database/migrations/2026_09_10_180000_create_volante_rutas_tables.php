<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('volante_rutas')) {
            Schema::create('volante_rutas', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('departamento', 100);
                $table->string('estado', 20)->default('abierta');
                $table->decimal('origin_lat', 10, 7);
                $table->decimal('origin_lng', 10, 7);
                $table->unsignedTinyInteger('max_paradas')->default(18);
                $table->unsignedSmallInteger('paradas_count')->default(0);
                $table->decimal('km', 8, 1)->nullable();
                $table->unsignedSmallInteger('minutos')->nullable();
                $table->json('polyline')->nullable();
                $table->uuid('creado_por_id')->nullable();
                $table->timestampTz('iniciado_at')->nullable();
                $table->timestampTz('completado_at')->nullable();
                $table->timestampsTz();

                $table->index(['estado', 'created_at']);
                $table->index('departamento');
            });
        }

        if (! Schema::hasTable('volante_ruta_paradas')) {
            Schema::create('volante_ruta_paradas', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('ruta_id')->constrained('volante_rutas')->cascadeOnDelete();
                $table->foreignUuid('prospecto_id')->constrained('veterinaria_prospectos')->cascadeOnDelete();
                $table->unsignedSmallInteger('orden');
                $table->string('nombre', 200);
                $table->string('direccion', 300)->nullable();
                $table->string('distrito', 100)->nullable();
                $table->string('telefono', 40)->nullable();
                $table->decimal('lat', 10, 7);
                $table->decimal('lng', 10, 7);
                $table->decimal('km_desde_anterior', 8, 2)->default(0);
                $table->timestampTz('visitado_at')->nullable();
                $table->string('resultado', 20)->nullable();
                $table->timestampsTz();

                $table->unique(['ruta_id', 'prospecto_id']);
                $table->index(['ruta_id', 'orden']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('volante_ruta_paradas');
        Schema::dropIfExists('volante_rutas');
    }
};
