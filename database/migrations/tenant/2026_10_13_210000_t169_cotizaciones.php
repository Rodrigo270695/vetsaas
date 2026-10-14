<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotizaciones de caja: productos y servicios, sin mover stock ni cobrar.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('cotizaciones')) {
                Schema::create('cotizaciones', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('numero', 30)->unique();
                    $table->unsignedSmallInteger('anio');
                    $table->unsignedInteger('correlativo');
                    /** UUID en `public.sedes`. */
                    $table->uuid('sede_id')->nullable();
                    $table->foreignUuid('propietario_id')->nullable()->constrained('propietarios')->nullOnDelete();
                    $table->foreignUuid('paciente_id')->nullable()->constrained('pacientes')->nullOnDelete();
                    $table->string('cliente_nombre', 200);
                    $table->string('cliente_documento', 30)->nullable();
                    $table->string('cliente_telefono', 30)->nullable();
                    $table->string('cliente_email', 150)->nullable();
                    $table->date('fecha');
                    $table->date('vigencia_hasta');
                    $table->string('estado', 20)->default('emitida');
                    $table->string('moneda', 3)->default('PEN');
                    $table->decimal('igv_porcentaje', 5, 2)->default(0);
                    $table->boolean('precio_incluye_igv')->default(true);
                    $table->decimal('subtotal', 12, 2)->default(0);
                    $table->decimal('igv_monto', 12, 2)->default(0);
                    $table->decimal('descuento_monto', 12, 2)->default(0);
                    $table->decimal('total', 12, 2)->default(0);
                    $table->text('notas')->nullable();
                    $table->timestampTz('enviada_whatsapp_at')->nullable();
                    $table->timestampTz('enviada_email_at')->nullable();
                    $table->foreignUuid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignUuid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestampsTz();
                    $table->softDeletesTz();

                    $table->unique(['anio', 'correlativo']);
                    $table->index(['fecha', 'estado']);
                });
            }

            if (! Schema::hasTable('cotizacion_lineas')) {
                Schema::create('cotizacion_lineas', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
                    $table->string('tipo_linea', 20)->default('producto');
                    $table->foreignUuid('producto_id')->nullable()->constrained('productos')->nullOnDelete();
                    $table->string('descripcion', 300);
                    $table->string('unidad', 30)->nullable();
                    $table->decimal('cantidad', 12, 3);
                    $table->decimal('precio_unitario', 12, 4);
                    $table->decimal('descuento_pct', 5, 2)->default(0);
                    $table->decimal('total', 12, 2);
                    $table->unsignedSmallInteger('orden')->default(0);

                    $table->index(['cotizacion_id', 'orden']);
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('cotizacion_lineas');
            Schema::dropIfExists('cotizaciones');
        });
    }
};
