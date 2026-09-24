<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('internamientos')) {
                return;
            }

            if (! Schema::hasTable('internamiento_notas')) {
                Schema::create('internamiento_notas', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('internamiento_id')->constrained('internamientos')->cascadeOnDelete();
                    $table->timestampTz('registrado_at');
                    $table->text('cuerpo');
                    $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignUuid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestampsTz();
                    $table->softDeletesTz();
                    $table->index(['internamiento_id', 'registrado_at']);
                });
            }

            if (! Schema::hasTable('internamiento_fluidos')) {
                Schema::create('internamiento_fluidos', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('internamiento_id')->constrained('internamientos')->cascadeOnDelete();
                    $table->timestampTz('registrado_at');
                    $table->string('tipo', 32)->nullable();
                    $table->string('solucion', 120)->nullable();
                    $table->string('via', 32)->nullable();
                    $table->decimal('volumen_ml', 8, 1)->nullable();
                    $table->decimal('velocidad_ml_h', 8, 2)->nullable();
                    $table->decimal('goteo_gtt_min', 6, 1)->nullable();
                    $table->decimal('duracion_horas', 5, 1)->nullable();
                    $table->text('aditivos')->nullable();
                    $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignUuid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestampsTz();
                    $table->softDeletesTz();
                    $table->index(['internamiento_id', 'registrado_at']);
                });
            }

            if (! Schema::hasTable('internamiento_tratamientos')) {
                Schema::create('internamiento_tratamientos', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('internamiento_id')->constrained('internamientos')->cascadeOnDelete();
                    $table->foreignUuid('servicio_clinico_id')->nullable()->constrained('servicios_clinicos')->nullOnDelete();
                    $table->timestampTz('registrado_at');
                    $table->string('detalle', 2000)->nullable();
                    $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignUuid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestampsTz();
                    $table->softDeletesTz();
                    $table->index(['internamiento_id', 'registrado_at']);
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('internamiento_tratamientos');
            Schema::dropIfExists('internamiento_fluidos');
            Schema::dropIfExists('internamiento_notas');
        });
    }
};
