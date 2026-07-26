<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo HC v2: médico tratante, catálogo de fármacos (por tenant),
 * exámenes complementarios y líneas de plan terapéutico en la consulta.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('consultas') && ! Schema::hasColumn('consultas', 'medico_tratante')) {
                Schema::table('consultas', function (Blueprint $table): void {
                    $table->string('medico_tratante', 200)->nullable()->after('veterinario_id');
                });
            }

            if (! Schema::hasTable('farmacos')) {
                Schema::create('farmacos', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('nombre', 200);
                    $table->timestampsTz();

                    $table->unique('nombre');
                });
            }

            if (! Schema::hasTable('consulta_examenes')) {
                Schema::create('consulta_examenes', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('consulta_id')
                        ->constrained('consultas')
                        ->cascadeOnDelete();
                    $table->foreignUuid('servicio_clinico_id')
                        ->nullable()
                        ->constrained('servicios_clinicos')
                        ->nullOnDelete();
                    $table->string('nombre', 500);
                    $table->unsignedSmallInteger('orden')->default(0);
                    $table->timestampsTz();

                    $table->index(['consulta_id', 'orden']);
                });
            }

            if (! Schema::hasTable('consulta_terapia_lineas')) {
                Schema::create('consulta_terapia_lineas', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('consulta_id')
                        ->constrained('consultas')
                        ->cascadeOnDelete();
                    $table->foreignUuid('farmaco_id')
                        ->nullable()
                        ->constrained('farmacos')
                        ->nullOnDelete();
                    $table->string('farmaco_nombre', 200);
                    $table->string('dosis_volumen', 200)->nullable();
                    $table->unsignedSmallInteger('orden')->default(0);
                    $table->timestampsTz();

                    $table->index(['consulta_id', 'orden']);
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('consulta_terapia_lineas');
            Schema::dropIfExists('consulta_examenes');
            Schema::dropIfExists('farmacos');

            if (Schema::hasTable('consultas') && Schema::hasColumn('consultas', 'medico_tratante')) {
                Schema::table('consultas', function (Blueprint $table): void {
                    $table->dropColumn('medico_tratante');
                });
            }
        });
    }
};
