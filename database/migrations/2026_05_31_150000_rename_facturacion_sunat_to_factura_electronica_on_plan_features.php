<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica la clave de feature con {@see \App\Models\Plan::FEATURE_CATALOG}
 * (`factura_electronica`), usada por el modal de features en Plataforma → Planes.
 *
 * Los seeds antiguos usaban `facturacion_sunat`; sin este paso, el valor
 * quedaría huérfano y no se podría editar desde la UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_features')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('
                DELETE FROM plan_features old
                USING plan_features neu
                WHERE old.feature = \'facturacion_sunat\'
                  AND neu.feature = \'factura_electronica\'
                  AND neu.plan_id = old.plan_id
            ');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('
                DELETE old FROM plan_features old
                INNER JOIN plan_features neu
                  ON neu.plan_id = old.plan_id AND neu.feature = \'factura_electronica\'
                WHERE old.feature = \'facturacion_sunat\'
            ');
        } else {
            $legacyIds = DB::table('plan_features')
                ->where('feature', 'facturacion_sunat')
                ->pluck('plan_id');

            foreach ($legacyIds as $planId) {
                $hasNew = DB::table('plan_features')
                    ->where('plan_id', $planId)
                    ->where('feature', 'factura_electronica')
                    ->exists();

                if ($hasNew) {
                    DB::table('plan_features')
                        ->where('plan_id', $planId)
                        ->where('feature', 'facturacion_sunat')
                        ->delete();
                }
            }
        }

        DB::table('plan_features')
            ->where('feature', 'facturacion_sunat')
            ->update(['feature' => 'factura_electronica']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('plan_features')) {
            return;
        }

        DB::table('plan_features')
            ->where('feature', 'factura_electronica')
            ->update(['feature' => 'facturacion_sunat']);
    }
};
