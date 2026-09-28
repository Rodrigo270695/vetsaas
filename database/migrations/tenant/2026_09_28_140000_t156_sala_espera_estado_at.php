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
            foreach (['citas', 'grooming_turnos'] as $table) {
                if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'sala_espera_estado_at')) {
                    Schema::table($table, function (Blueprint $blueprint): void {
                        $blueprint->timestampTz('sala_espera_estado_at')->nullable();
                    });
                }

                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sala_espera_estado_at')) {
                    continue;
                }

                if (! Schema::hasColumn($table, 'sala_espera_estado')) {
                    continue;
                }

                DB::table($table)
                    ->whereNull('sala_espera_estado_at')
                    ->whereIn('sala_espera_estado', ['atendido', 'cancelado'])
                    ->update([
                        'sala_espera_estado_at' => DB::raw('COALESCE(sala_espera_atendido_at, NOW())'),
                    ]);

                DB::table($table)
                    ->whereNull('sala_espera_estado_at')
                    ->where('sala_espera_estado', 'en_atencion')
                    ->update(['sala_espera_estado_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            foreach (['citas', 'grooming_turnos'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'sala_espera_estado_at')) {
                    Schema::table($table, function (Blueprint $blueprint): void {
                        $blueprint->dropColumn('sala_espera_estado_at');
                    });
                }
            }
        });
    }
};
