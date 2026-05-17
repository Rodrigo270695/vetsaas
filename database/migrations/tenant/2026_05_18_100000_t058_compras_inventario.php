<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('compras', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('proveedor_id')
                    ->nullable()
                    ->constrained('proveedores')
                    ->nullOnDelete();
                /** UUID en `public.sedes` (sin FK cruzada entre schemas). */
                $table->uuid('sede_id');
                $table->date('fecha_documento');
                $table->string('numero_documento', 64)->nullable();
                $table->string('serie', 16)->nullable();
                $table->string('moneda', 3)->default('PEN');
                $table->decimal('total', 14, 2)->nullable();
                $table->text('notas')->nullable();
                $table->string('factura_path', 500)->nullable();
                $table->string('factura_original_name', 255)->nullable();
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

                $table->index(['sede_id', 'fecha_documento']);
                $table->index('proveedor_id');
            });

            Schema::create('compra_lineas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('compra_id')
                    ->constrained('compras')
                    ->cascadeOnDelete();
                $table->foreignUuid('producto_id')
                    ->constrained('productos')
                    ->restrictOnDelete();
                $table->decimal('cantidad', 14, 3);
                $table->decimal('costo_unitario', 14, 4)->nullable();
                $table->unsignedSmallInteger('orden')->default(0);

                $table->index('compra_id');
                $table->index('producto_id');
            });

            Schema::table('movimientos_inventario', function (Blueprint $table) {
                $table->foreignUuid('compra_id')
                    ->nullable()
                    ->after('producto_id')
                    ->constrained('compras')
                    ->nullOnDelete();
                $table->index('compra_id');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('movimientos_inventario', function (Blueprint $table) {
                $table->dropForeign(['compra_id']);
                $table->dropColumn('compra_id');
            });
            Schema::dropIfExists('compra_lineas');
            Schema::dropIfExists('compras');
        });
    }
};
