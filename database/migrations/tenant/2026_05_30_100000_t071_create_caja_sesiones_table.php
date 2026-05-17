<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('caja_sesiones', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                /** UUID en `public.sedes` (sin FK cruzada entre schemas). */
                $table->uuid('sede_id');
                $table->string('estado', 16)->default('abierta');
                $table->char('moneda', 3)->default('PEN');
                /** Efectivo contado al abrir turno. */
                $table->decimal('saldo_apertura', 14, 2)->default(0);
                /** Efectivo contado al cerrar (null mientras la sesión siga abierta). */
                $table->decimal('saldo_cierre_efectivo', 14, 2)->nullable();
                $table->timestampTz('opened_at')->useCurrent();
                $table->timestampTz('closed_at')->nullable();
                $table->text('notas')->nullable();
                $table->foreignUuid('opened_by_id')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->foreignUuid('closed_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampsTz();

                $table->index(['sede_id', 'estado']);
                $table->index(['opened_by_id', 'opened_at']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('caja_sesiones');
        });
    }
};
