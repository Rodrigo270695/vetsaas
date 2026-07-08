<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('grooming_insumos')) {
                Schema::create('grooming_insumos', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('nombre', 150);
                    $table->boolean('activo')->default(true);
                    $table->timestampsTz();

                    $table->unique('nombre');
                });
            }

            if (! Schema::hasTable('grooming_servicio_insumo')) {
                Schema::create('grooming_servicio_insumo', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('grooming_servicio_id')
                        ->constrained('grooming_servicios')
                        ->cascadeOnDelete();
                    $table->foreignUuid('grooming_insumo_id')
                        ->constrained('grooming_insumos')
                        ->cascadeOnDelete();
                    $table->decimal('precio', 12, 2)->default(0);
                    $table->timestampsTz();

                    $table->unique(['grooming_servicio_id', 'grooming_insumo_id'], 'grooming_servicio_insumo_unique');
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('grooming_servicio_insumo');
            Schema::dropIfExists('grooming_insumos');
        });
    }
};
