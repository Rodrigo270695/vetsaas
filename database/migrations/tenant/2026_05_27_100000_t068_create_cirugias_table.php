<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('cirugias', function (Blueprint $table) {
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
                $table->timestampTz('programada_at');
                $table->string('estado', 24)->default('borrador');
                $table->string('nombre_procedimiento', 500);
                $table->string('tipo_anestesia', 120)->nullable();
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

                $table->index(['programada_at', 'estado']);
                $table->index('paciente_id');
                $table->index('consulta_id');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('cirugias');
        });
    }
};
