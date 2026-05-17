<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('hotel_estancias', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('paciente_id')
                    ->constrained('pacientes')
                    ->cascadeOnDelete();
                $table->foreignUuid('responsable_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignUuid('sede_id')
                    ->nullable()
                    ->constrained('sedes')
                    ->nullOnDelete();
                $table->timestampTz('ingreso_at');
                $table->timestampTz('egreso_at')->nullable();
                $table->string('estado', 32)->default('programada');
                $table->string('tipo_estancia', 100);
                $table->text('tipo_detalle')->nullable();
                $table->text('notas')->nullable();
                $table->foreignUuid('venta_id')
                    ->nullable()
                    ->constrained('ventas')
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

                $table->index(['ingreso_at', 'estado']);
                $table->index('paciente_id');
                $table->index(['estado', 'egreso_at']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('hotel_estancias');
        });
    }
};
