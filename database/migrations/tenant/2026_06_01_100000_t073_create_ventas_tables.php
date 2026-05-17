<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('ventas', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('numero', 24)->unique();
                $table->unsignedSmallInteger('anio');
                $table->unsignedInteger('correlativo');
                $table->unique(['anio', 'correlativo']);

                $table->foreignUuid('propietario_id')
                    ->constrained('propietarios')
                    ->restrictOnDelete();
                $table->foreignUuid('paciente_id')
                    ->nullable()
                    ->constrained('pacientes')
                    ->nullOnDelete();

                $table->foreignUuid('caja_sesion_id')
                    ->constrained('caja_sesiones')
                    ->restrictOnDelete();
                /** UUID en `public.sedes` (denormalizado desde la sesión). */
                $table->uuid('sede_id');

                $table->char('moneda', 3)->default('PEN');

                $table->string('estado', 20)->default('pendiente');

                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('igv_monto', 14, 2)->default(0);
                $table->decimal('descuento_monto', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);

                $table->string('metodo_pago', 24)->nullable();
                $table->decimal('monto_recibido', 14, 2)->nullable();
                $table->decimal('vuelto', 14, 2)->nullable();
                $table->timestampTz('fecha_pago')->nullable();

                $table->text('notas')->nullable();

                $table->string('fel_estado', 24)->default('sin_cpe');
                $table->uuid('fel_document_id')->nullable();

                $table->foreignUuid('created_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampsTz();
                $table->softDeletesTz();

                $table->index(['sede_id', 'created_at']);
                $table->index('estado');
                $table->index('propietario_id');
            });

            Schema::create('venta_lineas', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('venta_id')
                    ->constrained('ventas')
                    ->cascadeOnDelete();
                $table->foreignUuid('producto_id')
                    ->constrained('productos')
                    ->restrictOnDelete();
                $table->string('descripcion_snapshot', 300);
                $table->string('igv_tipo_snapshot', 20)->default('gravado');
                $table->decimal('cantidad', 14, 3);
                /** Precio unitario sin IGV (snapshot). */
                $table->decimal('precio_unitario', 14, 4);
                $table->decimal('descuento_pct', 7, 2)->default(0);
                /** Subtotal de línea sin IGV. */
                $table->decimal('subtotal', 14, 2);

                $table->index('venta_id');
            });

            Schema::table('movimientos_inventario', function (Blueprint $table): void {
                $table->foreignUuid('venta_id')
                    ->nullable()
                    ->after('compra_id')
                    ->constrained('ventas')
                    ->nullOnDelete();
                $table->index('venta_id');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('movimientos_inventario', function (Blueprint $table): void {
                $table->dropForeign(['venta_id']);
                $table->dropColumn('venta_id');
            });
            Schema::dropIfExists('venta_lineas');
            Schema::dropIfExists('ventas');
        });
    }
};
