<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('ventas', function (Blueprint $table): void {
                $table->foreignUuid('consulta_id')
                    ->nullable()
                    ->after('paciente_id')
                    ->constrained('consultas')
                    ->nullOnDelete();
                $table->foreignUuid('consulta_cargo_id')
                    ->nullable()
                    ->after('consulta_id')
                    ->constrained('consulta_cargos')
                    ->nullOnDelete();
                $table->index('consulta_id');
            });

            if (Schema::hasColumn('venta_lineas', 'producto_id')) {
                DB::statement('ALTER TABLE venta_lineas ALTER COLUMN producto_id DROP NOT NULL');
            }

            Schema::table('venta_lineas', function (Blueprint $table): void {
                if (! Schema::hasColumn('venta_lineas', 'tipo_linea')) {
                    $table->string('tipo_linea', 16)->nullable()->after('venta_id');
                }
                if (! Schema::hasColumn('venta_lineas', 'consulta_cargo_linea_id')) {
                    $table->foreignUuid('consulta_cargo_linea_id')
                        ->nullable()
                        ->after('producto_id')
                        ->constrained('consulta_cargo_lineas')
                        ->nullOnDelete();
                }
            });

            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->foreign('venta_id')
                    ->references('id')
                    ->on('ventas')
                    ->nullOnDelete();
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('consulta_cargos', function (Blueprint $table): void {
                $table->dropForeign(['venta_id']);
            });

            Schema::table('venta_lineas', function (Blueprint $table): void {
                $table->dropForeign(['consulta_cargo_linea_id']);
                $table->dropColumn(['tipo_linea', 'consulta_cargo_linea_id']);
            });

            DB::statement('ALTER TABLE venta_lineas ALTER COLUMN producto_id SET NOT NULL');

            Schema::table('ventas', function (Blueprint $table): void {
                $table->dropForeign(['consulta_id']);
                $table->dropForeign(['consulta_cargo_id']);
                $table->dropColumn(['consulta_id', 'consulta_cargo_id']);
            });
        });
    }
};
