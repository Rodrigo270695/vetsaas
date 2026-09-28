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
            if (Schema::hasTable('citas') && ! Schema::hasColumn('citas', 'sala_espera_estado')) {
                Schema::table('citas', function (Blueprint $table): void {
                    $table->string('sala_espera_estado', 20)->nullable();
                });
            }

            if (Schema::hasTable('grooming_turnos') && ! Schema::hasColumn('grooming_turnos', 'sala_espera_estado')) {
                Schema::table('grooming_turnos', function (Blueprint $table): void {
                    $table->string('sala_espera_estado', 20)->nullable();
                });
            }

            if (Schema::hasTable('citas') && Schema::hasColumn('citas', 'sala_espera_estado')) {
                DB::statement(<<<'SQL'
                    UPDATE citas
                    SET sala_espera_estado = CASE
                        WHEN sala_espera_atendido_at IS NOT NULL AND estado = 'cancelada' THEN 'cancelado'
                        WHEN sala_espera_atendido_at IS NOT NULL THEN 'atendido'
                        WHEN estado = 'en_atencion' THEN 'en_atencion'
                        ELSE 'citado'
                    END
                    WHERE sala_espera_enviado_at IS NOT NULL
                      AND sala_espera_estado IS NULL
                SQL);
            }

            if (Schema::hasTable('grooming_turnos') && Schema::hasColumn('grooming_turnos', 'sala_espera_estado')) {
                DB::statement(<<<'SQL'
                    UPDATE grooming_turnos
                    SET sala_espera_estado = CASE
                        WHEN sala_espera_atendido_at IS NOT NULL AND estado = 'cancelada' THEN 'cancelado'
                        WHEN sala_espera_atendido_at IS NOT NULL THEN 'atendido'
                        WHEN estado = 'en_proceso' THEN 'en_atencion'
                        ELSE 'citado'
                    END
                    WHERE sala_espera_enviado_at IS NOT NULL
                      AND sala_espera_estado IS NULL
                SQL);
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('citas') && Schema::hasColumn('citas', 'sala_espera_estado')) {
                Schema::table('citas', function (Blueprint $table): void {
                    $table->dropColumn('sala_espera_estado');
                });
            }

            if (Schema::hasTable('grooming_turnos') && Schema::hasColumn('grooming_turnos', 'sala_espera_estado')) {
                Schema::table('grooming_turnos', function (Blueprint $table): void {
                    $table->dropColumn('sala_espera_estado');
                });
            }
        });
    }
};
