<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Origen del egreso: efectivo, yape, plin o transferencia.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('caja_egresos')) {
                return;
            }

            Schema::table('caja_egresos', function (Blueprint $table): void {
                if (! Schema::hasColumn('caja_egresos', 'medio')) {
                    $table->string('medio', 24)->default('efectivo')->after('monto');
                }
            });

            if (Schema::hasColumn('caja_egresos', 'medio')) {
                DB::table('caja_egresos')
                    ->where(function ($q): void {
                        $q->whereNull('medio')->orWhere('medio', '');
                    })
                    ->update(['medio' => 'efectivo']);
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('caja_egresos') || ! Schema::hasColumn('caja_egresos', 'medio')) {
                return;
            }

            Schema::table('caja_egresos', function (Blueprint $table): void {
                $table->dropColumn('medio');
            });
        });
    }
};
