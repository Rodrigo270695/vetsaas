<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Support\Facades\DB;

/**
 * Quita la promoción seed «2ª mascota grooming −50%» creada en t097.
 * Los tenants nuevos ya no la reciben; esta migración limpia los existentes.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! DB::getSchemaBuilder()->hasTable('promotions')) {
                return;
            }

            DB::table('promotions')
                ->where('condition_type', 'second_pet_grooming')
                ->where('auto_apply', true)
                ->whereNull('code')
                ->where(function ($q): void {
                    $q->where('name', 'like', '2ª mascota grooming%')
                        ->orWhere('name', 'like', '2a mascota grooming%');
                })
                ->whereNull('deleted_at')
                ->update([
                    'is_active' => false,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        // No se restaura el seed automático.
    }
};
