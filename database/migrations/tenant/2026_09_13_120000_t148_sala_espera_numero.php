<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('citas') && ! Schema::hasColumn('citas', 'sala_espera_numero')) {
                Schema::table('citas', function (Blueprint $table): void {
                    $table->unsignedInteger('sala_espera_numero')->nullable();
                });
            }

            if (Schema::hasTable('grooming_turnos') && ! Schema::hasColumn('grooming_turnos', 'sala_espera_numero')) {
                Schema::table('grooming_turnos', function (Blueprint $table): void {
                    $table->unsignedInteger('sala_espera_numero')->nullable();
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('citas') && Schema::hasColumn('citas', 'sala_espera_numero')) {
                Schema::table('citas', function (Blueprint $table): void {
                    $table->dropColumn('sala_espera_numero');
                });
            }

            if (Schema::hasTable('grooming_turnos') && Schema::hasColumn('grooming_turnos', 'sala_espera_numero')) {
                Schema::table('grooming_turnos', function (Blueprint $table): void {
                    $table->dropColumn('sala_espera_numero');
                });
            }
        });
    }
};
