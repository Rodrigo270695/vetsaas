<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            foreach (['citas', 'grooming_turnos'] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                    if (! Schema::hasColumn($table, 'sala_espera_motivo')) {
                        $blueprint->text('sala_espera_motivo')->nullable();
                    }
                    if (! Schema::hasColumn($table, 'sala_espera_tipo_atencion')) {
                        $blueprint->string('sala_espera_tipo_atencion', 30)->nullable();
                    }
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            foreach (['citas', 'grooming_turnos'] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                    if (Schema::hasColumn($table, 'sala_espera_tipo_atencion')) {
                        $blueprint->dropColumn('sala_espera_tipo_atencion');
                    }
                    if (Schema::hasColumn($table, 'sala_espera_motivo')) {
                        $blueprint->dropColumn('sala_espera_motivo');
                    }
                });
            }
        });
    }
};
