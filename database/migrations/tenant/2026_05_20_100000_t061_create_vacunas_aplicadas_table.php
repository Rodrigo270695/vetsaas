<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('vacunas_aplicadas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('paciente_id')
                    ->constrained('pacientes')
                    ->cascadeOnDelete();
                $table->foreignUuid('producto_id')
                    ->nullable()
                    ->constrained('productos')
                    ->nullOnDelete();
                $table->string('nombre_vacuna', 500);
                $table->timestampTz('aplicada_at');
                $table->unsignedSmallInteger('numero_dosis')->nullable();
                $table->string('lote', 128)->nullable();
                $table->text('notas')->nullable();
                $table->foreignUuid('veterinario_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                /** UUID en `public.sedes` (sin FK entre schemas). */
                $table->uuid('sede_id')->nullable();
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

                $table->index(['paciente_id', 'aplicada_at']);
                $table->index('aplicada_at');
                $table->index('sede_id');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('vacunas_aplicadas');
        });
    }
};
