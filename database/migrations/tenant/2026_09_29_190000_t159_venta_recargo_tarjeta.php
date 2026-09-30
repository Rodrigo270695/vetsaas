<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recargo de tarjeta cobrado aparte del comprobante.
 * El ticket, la boleta y la factura conservan el precio original de cada línea.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('ventas')) {
                return;
            }

            Schema::table('ventas', function (Blueprint $table): void {
                if (! Schema::hasColumn('ventas', 'recargo_tarjeta_monto')) {
                    $table->decimal('recargo_tarjeta_monto', 12, 2)->default(0)->after('total');
                }
                if (! Schema::hasColumn('ventas', 'recargo_tarjeta_porcentaje')) {
                    $table->decimal('recargo_tarjeta_porcentaje', 5, 2)->nullable()->after('recargo_tarjeta_monto');
                }
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('ventas')) {
                return;
            }

            Schema::table('ventas', function (Blueprint $table): void {
                if (Schema::hasColumn('ventas', 'recargo_tarjeta_porcentaje')) {
                    $table->dropColumn('recargo_tarjeta_porcentaje');
                }
                if (Schema::hasColumn('ventas', 'recargo_tarjeta_monto')) {
                    $table->dropColumn('recargo_tarjeta_monto');
                }
            });
        });
    }
};
