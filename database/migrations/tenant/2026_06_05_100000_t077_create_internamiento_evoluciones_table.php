<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('internamiento_evoluciones', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('internamiento_id')
                    ->constrained('internamientos')
                    ->cascadeOnDelete();
                $table->timestampTz('registrado_at');
                $table->foreignUuid('veterinario_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->decimal('peso_kg', 7, 2)->nullable();
                $table->decimal('temperatura_c', 4, 1)->nullable();
                $table->unsignedSmallInteger('fc_lpm')->nullable();
                $table->unsignedSmallInteger('fr_rpm')->nullable();
                $table->text('evolucion');
                $table->text('tratamiento')->nullable();
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

                $table->index(['internamiento_id', 'registrado_at']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('internamiento_evoluciones');
        });
    }
};
