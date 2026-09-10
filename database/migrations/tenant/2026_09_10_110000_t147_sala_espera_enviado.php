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
            if (Schema::hasTable('citas') && ! Schema::hasColumn('citas', 'sala_espera_enviado_at')) {
                Schema::table('citas', function (Blueprint $table): void {
                    $table->timestampTz('sala_espera_enviado_at')->nullable();
                });
            }

            if (Schema::hasTable('grooming_turnos') && ! Schema::hasColumn('grooming_turnos', 'sala_espera_enviado_at')) {
                Schema::table('grooming_turnos', function (Blueprint $table): void {
                    $table->timestampTz('sala_espera_enviado_at')->nullable();
                });
            }

            if (Schema::hasTable('citas') && Schema::hasColumn('citas', 'sala_espera_enviado_at')) {
                DB::table('citas')
                    ->where('motivo', 'Sala de espera')
                    ->whereNull('sala_espera_enviado_at')
                    ->update(['sala_espera_enviado_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('citas') && Schema::hasColumn('citas', 'sala_espera_enviado_at')) {
                Schema::table('citas', function (Blueprint $table): void {
                    $table->dropColumn('sala_espera_enviado_at');
                });
            }

            if (Schema::hasTable('grooming_turnos') && Schema::hasColumn('grooming_turnos', 'sala_espera_enviado_at')) {
                Schema::table('grooming_turnos', function (Blueprint $table): void {
                    $table->dropColumn('sala_espera_enviado_at');
                });
            }
        });
    }
};
