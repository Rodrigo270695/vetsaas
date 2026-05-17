<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('movimientos_inventario', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('producto_id')
                    ->constrained('productos')
                    ->cascadeOnDelete();
                /** UUID en `public.sedes`. */
                $table->uuid('sede_id');
                $table->string('tipo', 24);
                /** Cambio aplicado (positivo = entra, negativo = sale). */
                $table->decimal('delta', 14, 3);
                $table->decimal('stock_anterior', 14, 3);
                $table->decimal('stock_despues', 14, 3);
                $table->text('notas')->nullable();
                $table->foreignUuid('created_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampTz('created_at');

                $table->index(['sede_id', 'created_at']);
                $table->index(['producto_id', 'created_at']);
                $table->index('tipo');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('movimientos_inventario');
        });
    }
};
