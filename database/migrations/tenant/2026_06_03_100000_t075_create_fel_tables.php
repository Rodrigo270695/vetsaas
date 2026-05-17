<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('fel_series', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                /** 1 = factura, 2 = boleta (códigos Nubefact / SUNAT). */
                $table->unsignedTinyInteger('tipo_comprobante');
                $table->string('serie', 4);
                $table->unsignedBigInteger('ultimo_correlativo')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestampsTz();

                $table->unique(['tipo_comprobante', 'serie']);
            });

            Schema::create('fel_documents', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('venta_id')
                    ->unique()
                    ->constrained('ventas')
                    ->cascadeOnDelete();
                $table->foreignUuid('fel_serie_id')
                    ->constrained('fel_series')
                    ->restrictOnDelete();

                $table->unsignedTinyInteger('tipo_comprobante');
                $table->string('serie', 4);
                $table->unsignedBigInteger('correlativo');
                $table->string('numero_completo', 20);

                $table->unsignedTinyInteger('receptor_tipo_doc');
                $table->string('receptor_num_doc', 15);
                $table->string('receptor_nombre', 200);

                $table->decimal('subtotal', 14, 2);
                $table->decimal('igv_monto', 14, 2);
                $table->decimal('total', 14, 2);
                $table->char('moneda', 3)->default('PEN');

                $table->string('estado', 24)->default('pendiente');
                $table->string('nubefact_id', 100)->nullable();
                $table->string('url_pdf', 500)->nullable();
                $table->string('url_xml', 500)->nullable();
                $table->string('url_cdr', 500)->nullable();
                $table->string('enlace_consulta', 500)->nullable();
                $table->text('error_mensaje')->nullable();
                $table->timestampTz('emitido_at')->nullable();
                $table->timestampsTz();

                $table->index('estado');
            });

            Schema::table('ventas', function (Blueprint $table): void {
                $table->foreign('fel_document_id')
                    ->references('id')
                    ->on('fel_documents')
                    ->nullOnDelete();
            });

            $now = now();
            DB::table('fel_series')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'tipo_comprobante' => 2,
                    'serie' => 'B001',
                    'ultimo_correlativo' => 0,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'tipo_comprobante' => 1,
                    'serie' => 'F001',
                    'ultimo_correlativo' => 0,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('ventas', function (Blueprint $table): void {
                $table->dropForeign(['fel_document_id']);
            });
            Schema::dropIfExists('fel_documents');
            Schema::dropIfExists('fel_series');
        });
    }
};
