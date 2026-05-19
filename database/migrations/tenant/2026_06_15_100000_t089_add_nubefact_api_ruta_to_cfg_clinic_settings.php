<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasColumn('cfg_clinic_settings', 'nubefact_api_ruta')) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                $table->string('nubefact_api_ruta', 500)->nullable()->after('nubefact_ruc');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasColumn('cfg_clinic_settings', 'nubefact_api_ruta')) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                $table->dropColumn('nubefact_api_ruta');
            });
        });
    }
};
