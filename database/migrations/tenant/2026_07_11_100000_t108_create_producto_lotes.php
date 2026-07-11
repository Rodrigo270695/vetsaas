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
            Schema::create('producto_lotes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('producto_id')
                    ->constrained('productos')
                    ->restrictOnDelete();
                $table->uuid('sede_id');
                $table->string('numero_lote', 128);
                $table->date('fecha_vencimiento')->nullable();
                $table->decimal('cantidad', 14, 3)->default(0);
                $table->foreignUuid('compra_linea_id')
                    ->nullable()
                    ->constrained('compra_lineas')
                    ->nullOnDelete();
                $table->timestampsTz();

                $table->index(['producto_id', 'sede_id']);
                $table->index(['producto_id', 'sede_id', 'fecha_vencimiento']);
            });

            Schema::table('compra_lineas', function (Blueprint $table) {
                $table->string('numero_lote', 128)->nullable()->after('costo_unitario');
                $table->date('fecha_vencimiento')->nullable()->after('numero_lote');
            });

            Schema::table('movimientos_inventario', function (Blueprint $table) {
                $table->foreignUuid('producto_lote_id')
                    ->nullable()
                    ->after('venta_id')
                    ->constrained('producto_lotes')
                    ->nullOnDelete();
            });

            Schema::table('consulta_plan_tratamiento_lineas', function (Blueprint $table) {
                $table->foreignUuid('movimiento_inventario_id')
                    ->nullable()
                    ->after('producto_id')
                    ->constrained('movimientos_inventario')
                    ->nullOnDelete();
            });

            Schema::table('consulta_cargo_lineas', function (Blueprint $table) {
                $table->foreignUuid('movimiento_inventario_id')
                    ->nullable()
                    ->after('producto_id')
                    ->constrained('movimientos_inventario')
                    ->nullOnDelete();
            });

            if (Schema::hasTable('existencias_sede')) {
                $existencias = DB::table('existencias_sede')
                    ->where('cantidad', '>', 0)
                    ->get(['producto_id', 'sede_id', 'cantidad']);

                foreach ($existencias as $row) {
                    DB::table('producto_lotes')->insert([
                        'id' => (string) Str::uuid(),
                        'producto_id' => $row->producto_id,
                        'sede_id' => $row->sede_id,
                        'numero_lote' => 'STOCK-INICIAL',
                        'fecha_vencimiento' => null,
                        'cantidad' => $row->cantidad,
                        'compra_linea_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('consulta_plan_tratamiento_lineas', function (Blueprint $table) {
                $table->dropConstrainedForeignId('movimiento_inventario_id');
            });

            Schema::table('consulta_cargo_lineas', function (Blueprint $table) {
                $table->dropConstrainedForeignId('movimiento_inventario_id');
            });

            Schema::table('movimientos_inventario', function (Blueprint $table) {
                $table->dropConstrainedForeignId('producto_lote_id');
            });

            Schema::table('compra_lineas', function (Blueprint $table) {
                $table->dropColumn(['numero_lote', 'fecha_vencimiento']);
            });

            Schema::dropIfExists('producto_lotes');
        });
    }
};
