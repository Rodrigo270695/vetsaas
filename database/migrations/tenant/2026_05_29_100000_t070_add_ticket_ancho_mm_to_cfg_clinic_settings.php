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
                $table->string('ticket_ancho_mm', 4)->default('80');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                $table->dropColumn('ticket_ancho_mm');
            });
        });
    }
};
