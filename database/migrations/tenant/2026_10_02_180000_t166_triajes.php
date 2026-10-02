<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha de triaje del paciente: constantes y listas de revisión.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('triajes') || ! Schema::hasTable('pacientes')) {
                return;
            }

            Schema::create('triajes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('paciente_id')->constrained('pacientes')->cascadeOnDelete();
                $table->timestampTz('atendido_at');
                $table->decimal('peso_kg', 8, 3)->nullable();
                $table->decimal('temperatura_c', 5, 2)->nullable();
                $table->unsignedSmallInteger('fc_lpm')->nullable();
                $table->unsignedSmallInteger('fr_rpm')->nullable();
                $table->decimal('tlc', 4, 1)->nullable();
                $table->decimal('pa', 6, 1)->nullable();
                $table->decimal('hidratacion', 5, 1)->nullable();
                $table->jsonb('hallazgos')->nullable();
                $table->jsonb('recomendaciones')->nullable();
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
            Schema::dropIfExists('triajes');
        });
    }
};
