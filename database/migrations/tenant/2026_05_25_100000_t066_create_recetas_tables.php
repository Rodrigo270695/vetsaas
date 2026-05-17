<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('recetas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('paciente_id')
                    ->constrained('pacientes')
                    ->cascadeOnDelete();
                $table->foreignUuid('consulta_id')
                    ->nullable()
                    ->constrained('consultas')
                    ->nullOnDelete();
                $table->foreignUuid('veterinario_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignUuid('sede_id')
                    ->nullable()
                    ->constrained('sedes')
                    ->nullOnDelete();
                $table->timestampTz('emitida_at');
                $table->string('estado', 24)->default('borrador');
                $table->text('observaciones')->nullable();
                $table->foreignUuid('created_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignUuid('updated_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampsTz();
                $table->softDeletesTz();

                $table->index(['emitida_at', 'estado']);
                $table->index('paciente_id');
                $table->index('consulta_id');
            });

            Schema::create('receta_lineas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('receta_id')
                    ->constrained('recetas')
                    ->cascadeOnDelete();
                $table->foreignUuid('producto_id')
                    ->nullable()
                    ->constrained('productos')
                    ->nullOnDelete();
                $table->string('nombre_medicamento', 500);
                $table->text('posologia')->nullable();
                $table->unsignedSmallInteger('duracion_dias')->nullable();
                $table->text('instrucciones')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestampsTz();

                $table->index(['receta_id', 'orden']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('receta_lineas');
            Schema::dropIfExists('recetas');
        });
    }
};
