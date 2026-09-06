<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldos de Yape / Plin / transferencia al abrir y cerrar caja.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('caja_sesiones')) {
                return;
            }

            Schema::table('caja_sesiones', function (Blueprint $table): void {
                if (! Schema::hasColumn('caja_sesiones', 'saldos_apertura_json')) {
                    $table->jsonb('saldos_apertura_json')->nullable()->after('saldo_apertura');
                }
                if (! Schema::hasColumn('caja_sesiones', 'saldos_cierre_json')) {
                    $table->jsonb('saldos_cierre_json')->nullable()->after('saldo_cierre_efectivo');
                }
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('caja_sesiones')) {
                return;
            }

            Schema::table('caja_sesiones', function (Blueprint $table): void {
                if (Schema::hasColumn('caja_sesiones', 'saldos_apertura_json')) {
                    $table->dropColumn('saldos_apertura_json');
                }
                if (Schema::hasColumn('caja_sesiones', 'saldos_cierre_json')) {
                    $table->dropColumn('saldos_cierre_json');
                }
            });
        });
    }
};
