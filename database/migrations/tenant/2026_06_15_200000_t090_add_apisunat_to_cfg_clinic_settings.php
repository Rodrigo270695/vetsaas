<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasColumn('cfg_clinic_settings', 'apisunat_token_enc')) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->text('apisunat_token_enc')->nullable()->after('emite_comprobantes_sunat');
                });
            }

            if (! Schema::hasColumn('cfg_clinic_settings', 'apisunat_mode')) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->string('apisunat_mode', 20)->default('sandbox')->after('apisunat_token_enc');
                });
            }

            if (! Schema::hasColumn('cfg_clinic_settings', 'apisunat_configurado')) {
                Schema::table('cfg_clinic_settings', function (Blueprint $table): void {
                    $table->boolean('apisunat_configurado')->default(false)->after('apisunat_mode');
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            $columns = array_values(array_filter(
                ['apisunat_token_enc', 'apisunat_mode', 'apisunat_configurado'],
                fn (string $column): bool => Schema::hasColumn('cfg_clinic_settings', $column),
            ));

            if ($columns === []) {
                return;
            }

            Schema::table('cfg_clinic_settings', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        });
    }
};
