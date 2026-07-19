<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends TenantMigration
{
    /**
     * Catálogo de servicios clínicos por clínica + categorías reutilizables (create on-the-fly).
     */
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('categorias_servicio_clinico')) {
                Schema::create('categorias_servicio_clinico', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('nombre', 80);
                    $table->boolean('activo')->default(true);
                    $table->timestampsTz();
                    $table->softDeletesTz();

                    $table->unique('nombre');
                    $table->index(['activo']);
                });

                $now = now();
                foreach (['Consulta', 'Tratamiento', 'Cirugía', 'Vacunación', 'Procedimiento'] as $nombre) {
                    DB::table('categorias_servicio_clinico')->insert([
                        'id' => (string) Str::uuid(),
                        'nombre' => $nombre,
                        'activo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            if (! Schema::hasTable('servicios_clinicos')) {
                Schema::create('servicios_clinicos', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('nombre', 200);
                    $table->foreignUuid('categoria_id')
                        ->nullable()
                        ->constrained('categorias_servicio_clinico')
                        ->nullOnDelete();
                    $table->decimal('precio_lista', 12, 2)->default(0);
                    $table->char('moneda', 3)->default('PEN');
                    $table->unsignedSmallInteger('duracion_minutos')->nullable();
                    $table->boolean('activo')->default(true);
                    $table->unsignedSmallInteger('orden')->default(0);
                    $table->timestampsTz();

                    $table->index(['activo', 'orden']);
                    $table->index('nombre');
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('servicios_clinicos');
            Schema::dropIfExists('categorias_servicio_clinico');
        });
    }
};
