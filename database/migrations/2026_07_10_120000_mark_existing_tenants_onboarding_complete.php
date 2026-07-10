<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')
            ->where('onboarding_completado', false)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('sedes')
                    ->whereColumn('sedes.tenant_id', 'tenants.id')
                    ->where('sedes.activa', true)
                    ->whereNull('sedes.deleted_at');
            })
            ->update([
                'onboarding_completado' => true,
                'onboarding_paso' => 5,
            ]);
    }

    public function down(): void
    {
        // No revertimos: los tenants operativos no deben volver al wizard automáticamente.
    }
};
