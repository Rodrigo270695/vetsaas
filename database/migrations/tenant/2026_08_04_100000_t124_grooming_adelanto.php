<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adelanto (anticipo) de grooming: venta de anticipo vinculada al turno,
 * sin marcar el cobro final (venta_id / cargo.venta_id).
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('grooming_turnos')) {
                return;
            }

            Schema::table('grooming_turnos', function (Blueprint $table): void {
                if (! Schema::hasColumn('grooming_turnos', 'adelanto_venta_id')) {
                    $table->foreignUuid('adelanto_venta_id')
                        ->nullable()
                        ->after('venta_id')
                        ->constrained('ventas')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('grooming_turnos', 'adelanto_monto')) {
                    $table->decimal('adelanto_monto', 14, 2)->nullable()->after('adelanto_venta_id');
                }
                if (! Schema::hasColumn('grooming_turnos', 'adelanto_at')) {
                    $table->timestampTz('adelanto_at')->nullable()->after('adelanto_monto');
                }
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('grooming_turnos')) {
                return;
            }

            Schema::table('grooming_turnos', function (Blueprint $table): void {
                if (Schema::hasColumn('grooming_turnos', 'adelanto_venta_id')) {
                    $table->dropConstrainedForeignId('adelanto_venta_id');
                }
                if (Schema::hasColumn('grooming_turnos', 'adelanto_monto')) {
                    $table->dropColumn('adelanto_monto');
                }
                if (Schema::hasColumn('grooming_turnos', 'adelanto_at')) {
                    $table->dropColumn('adelanto_at');
                }
            });
        });
    }
};
