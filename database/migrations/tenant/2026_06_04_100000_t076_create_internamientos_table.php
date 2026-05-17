<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('internamientos', function (Blueprint $table) {
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
                $table->timestampTz('ingreso_at');
                $table->timestampTz('alta_at')->nullable();
                $table->string('estado', 24)->default('activo');
                $table->string('motivo_ingreso', 500);
                $table->string('ubicacion', 120)->nullable();
                $table->text('diagnostico_ingreso')->nullable();
                $table->text('notas')->nullable();
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

                $table->index(['ingreso_at', 'estado']);
                $table->index('paciente_id');
                $table->index('consulta_id');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('internamientos');
        });
    }
};
