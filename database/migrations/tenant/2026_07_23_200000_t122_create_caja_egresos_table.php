<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('caja_egresos', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('caja_sesion_id')
                    ->constrained('caja_sesiones')
                    ->cascadeOnDelete();
                /** Salida de efectivo de la caja física del turno. */
                $table->decimal('monto', 14, 2);
                $table->string('motivo', 32);
                $table->text('notas')->nullable();
                $table->foreignUuid('created_by_id')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->timestampsTz();

                $table->index(['caja_sesion_id', 'created_at']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('caja_egresos');
        });
    }
};
