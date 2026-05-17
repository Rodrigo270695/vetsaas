<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('consulta_cargos', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('consulta_id')
                    ->unique()
                    ->constrained('consultas')
                    ->cascadeOnDelete();
                $table->string('estado', 24)->default('borrador');
                $table->char('moneda', 3)->default('PEN');
                $table->text('notas')->nullable();
                $table->decimal('subtotal_sin_igv', 14, 2)->default(0);
                $table->decimal('igv_importe', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->uuid('venta_id')->nullable();
                $table->foreignUuid('created_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignUuid('updated_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampsTz();

                $table->index('estado');
            });

            Schema::create('consulta_cargo_lineas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('consulta_cargo_id')
                    ->constrained('consulta_cargos')
                    ->cascadeOnDelete();
                $table->string('tipo_linea', 16);
                $table->foreignUuid('producto_id')
                    ->nullable()
                    ->constrained('productos')
                    ->nullOnDelete();
                $table->string('concepto', 500);
                $table->decimal('cantidad', 12, 4);
                $table->decimal('precio_unitario', 14, 4);
                $table->decimal('descuento_importe', 14, 2)->default(0);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestampsTz();

                $table->index(['consulta_cargo_id', 'orden']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('consulta_cargo_lineas');
            Schema::dropIfExists('consulta_cargos');
        });
    }
};
