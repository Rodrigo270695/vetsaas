<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('vacunas_aplicadas', function (Blueprint $table): void {
                $table->string('categoria_registro', 24)->default('vacuna')->after('lote');
                $table->text('esquema_antigenos')->nullable()->after('categoria_registro');
                $table->date('fecha_proxima_sugerida')->nullable()->after('esquema_antigenos');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('vacunas_aplicadas', function (Blueprint $table): void {
                $table->dropColumn(['categoria_registro', 'esquema_antigenos', 'fecha_proxima_sugerida']);
            });
        });
    }
};
