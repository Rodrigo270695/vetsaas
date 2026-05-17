<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                $table->boolean('emite_comprobantes_sunat')->default(false)->after('ticket_ancho_mm');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                $table->dropColumn('emite_comprobantes_sunat');
            });
        });
    }
};
