<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('consulta_planes_tratamiento', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('consulta_id')
                    ->unique()
                    ->constrained('consultas')
                    ->cascadeOnDelete();
                $table->date('fecha_inicio')->nullable();
                $table->date('fecha_fin')->nullable();
                $table->text('indicaciones')->nullable();
                $table->string('estado', 32)->default('activo');
                $table->foreignUuid('created_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignUuid('updated_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampsTz();

                $table->index('estado');
            });

            Schema::create('consulta_plan_tratamiento_lineas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('plan_id')
                    ->constrained('consulta_planes_tratamiento')
                    ->cascadeOnDelete();
                $table->string('medicamento', 500);
                $table->string('dosis', 255)->nullable();
                $table->string('unidad', 64)->nullable();
                $table->string('via', 128)->nullable();
                $table->string('frecuencia', 255)->nullable();
                $table->string('lote', 128)->nullable();
                $table->text('notas')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestampsTz();

                $table->index(['plan_id', 'sort_order']);
            });

            Schema::create('consulta_plan_tratamiento_seguimientos', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('plan_id')
                    ->constrained('consulta_planes_tratamiento')
                    ->cascadeOnDelete();
                $table->timestampTz('registrado_at');
                $table->text('nota');
                $table->foreignUuid('created_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampsTz();

                $table->index(['plan_id', 'registrado_at']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('consulta_plan_tratamiento_seguimientos');
            Schema::dropIfExists('consulta_plan_tratamiento_lineas');
            Schema::dropIfExists('consulta_planes_tratamiento');
        });
    }
};
