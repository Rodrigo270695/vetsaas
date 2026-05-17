<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('consultas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('historia_clinica_id')
                    ->constrained('historias_clinicas')
                    ->cascadeOnDelete();
                $table->timestampTz('atendido_at');
                $table->text('motivo')->nullable();
                $table->text('subjetivo')->nullable();
                $table->text('objetivo')->nullable();
                $table->text('analisis')->nullable();
                $table->text('plan')->nullable();
                $table->decimal('peso_kg', 7, 2)->nullable();
                $table->foreignUuid('veterinario_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
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

                $table->index('historia_clinica_id');
                $table->index('atendido_at');
                $table->index('veterinario_id');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('consultas');
        });
    }
};
