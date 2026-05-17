<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('citas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('paciente_id')
                    ->constrained('pacientes')
                    ->cascadeOnDelete();
                $table->foreignUuid('veterinario_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignUuid('sede_id')
                    ->nullable()
                    ->constrained('sedes')
                    ->nullOnDelete();
                $table->timestampTz('inicio_at');
                $table->unsignedSmallInteger('duracion_minutos')->default(30);
                $table->string('estado', 32)->default('programada');
                $table->text('motivo')->nullable();
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

                $table->index(['inicio_at', 'estado']);
                $table->index('paciente_id');
                $table->index('veterinario_id');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('citas');
        });
    }
};
