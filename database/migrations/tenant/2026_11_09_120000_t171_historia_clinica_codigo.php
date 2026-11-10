<?php

use App\Database\Migrations\TenantMigration;
use App\Models\Paciente;
use App\Support\Clinica\HistoriaClinicaCodigo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('historias_clinicas')) {
                return;
            }

            if (! Schema::hasColumn('historias_clinicas', 'codigo')) {
                Schema::table('historias_clinicas', function (Blueprint $table): void {
                    $table->string('codigo', 32)->nullable()->unique();
                });
            }

            Paciente::query()
                ->with('propietario:id,numero_documento')
                ->orderBy('created_at')
                ->orderBy('id')
                ->each(function (Paciente $paciente): void {
                    HistoriaClinicaCodigo::ensure($paciente);
                });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('historias_clinicas') && Schema::hasColumn('historias_clinicas', 'codigo')) {
                Schema::table('historias_clinicas', function (Blueprint $table): void {
                    $table->dropUnique(['codigo']);
                    $table->dropColumn('codigo');
                });
            }
        });
    }
};
