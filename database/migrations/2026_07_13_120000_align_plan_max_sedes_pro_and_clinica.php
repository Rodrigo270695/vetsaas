<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alinea max_sedes con el producto comercial:
 * - Pro: 1 sede
 * - Clínica: hasta 3 sedes
 *
 * El seeder anterior dejaba Pro en 3 e ilimitado en Clínica, por eso
 * tenants Pro seguían viendo «+ Nueva sede» con 1 sede ya creada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->updateMaxSedes('pro', 1);
        $this->updateMaxSedes('clinica', 3);

        DB::table('plan_features')
            ->where('feature', 'multi_sede')
            ->whereIn('plan_id', $this->planIds(['pro', 'starter', 'free']))
            ->update(['valor_bool' => false]);

        DB::table('plan_features')
            ->where('feature', 'multi_sede')
            ->whereIn('plan_id', $this->planIds(['clinica']))
            ->update(['valor_bool' => true]);
    }

    public function down(): void
    {
        $this->updateMaxSedes('pro', 3);
        $this->updateMaxSedes('clinica', -1);
    }

    private function updateMaxSedes(string $codigo, int $valor): void
    {
        $ids = $this->planIds([$codigo]);

        if ($ids === []) {
            return;
        }

        DB::table('plan_features')
            ->where('feature', 'max_sedes')
            ->whereIn('plan_id', $ids)
            ->update(['valor_int' => $valor]);
    }

    /**
     * @param  list<string>  $codigos
     * @return list<string>
     */
    private function planIds(array $codigos): array
    {
        return DB::table('plans')
            ->whereIn('codigo', $codigos)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }
};
