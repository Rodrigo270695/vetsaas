<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasColumn('cfg_clinic_settings', 'bot_ia_respuestas_activo')) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                $table->boolean('bot_ia_respuestas_activo')->default(true);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasColumn('cfg_clinic_settings', 'bot_ia_respuestas_activo')) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                $table->dropColumn('bot_ia_respuestas_activo');
            });
        });
    }
};
