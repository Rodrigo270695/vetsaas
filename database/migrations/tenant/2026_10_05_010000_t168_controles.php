<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha de control clínico, aparte de la consulta.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('controles')) {
                return;
            }

            Schema::create('controles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('paciente_id')->constrained('pacientes')->cascadeOnDelete();
                $table->timestampTz('atendido_at');
                $table->string('motivo', 200)->nullable();
                $table->text('anamnesis')->nullable();
                $table->text('anamnesis_detalle')->nullable();
                $table->decimal('peso_kg', 8, 3)->nullable();
                $table->decimal('temperatura_c', 5, 2)->nullable();
                $table->unsignedSmallInteger('fc_lpm')->nullable();
                $table->unsignedSmallInteger('fr_rpm')->nullable();
                $table->string('tlc', 40)->nullable();
                $table->string('pa', 40)->nullable();
                $table->string('hidratacion', 40)->nullable();
                $table->text('examen_clinico')->nullable();
                $table->text('examen_detalle')->nullable();
                $table->jsonb('diagnosticos')->nullable();
                $table->jsonb('examenes')->nullable();
                $table->jsonb('tratamiento')->nullable();
                $table->jsonb('receta')->nullable();
                $table->text('comentarios')->nullable();
                $table->foreignUuid('veterinario_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampsTz();
                $table->softDeletesTz();

                $table->index(['paciente_id', 'atendido_at']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('controles');
        });
    }
};
