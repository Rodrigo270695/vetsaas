<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha propia de desparasitación (antiparasitario), aparte de la vacunación.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('desparasitaciones')) {
                return;
            }

            Schema::create('desparasitaciones', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('paciente_id')->constrained('pacientes')->cascadeOnDelete();
                $table->timestampTz('atendido_at');
                $table->text('anamnesis')->nullable();
                $table->string('apetito', 20)->nullable();
                $table->string('ingesta_agua', 20)->nullable();
                $table->string('vomitos_frecuencia', 160)->nullable();
                $table->text('vomitos_descripcion')->nullable();
                $table->string('heces_frecuencia', 160)->nullable();
                $table->text('heces_descripcion')->nullable();
                $table->string('orina_frecuencia', 160)->nullable();
                $table->string('orina_color', 160)->nullable();
                $table->string('orina_olor', 160)->nullable();
                $table->date('ultimo_celo')->nullable();
                $table->decimal('peso_kg', 8, 3)->nullable();
                $table->decimal('temperatura_c', 5, 2)->nullable();
                $table->unsignedSmallInteger('fc_lpm')->nullable();
                $table->unsignedSmallInteger('fr_rpm')->nullable();
                $table->string('tlc', 40)->nullable();
                $table->string('pa', 40)->nullable();
                $table->string('hidratacion', 40)->nullable();
                $table->jsonb('aplicados')->nullable();
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
            Schema::dropIfExists('desparasitaciones');
        });
    }
};
