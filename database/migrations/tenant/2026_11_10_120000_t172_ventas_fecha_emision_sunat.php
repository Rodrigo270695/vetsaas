<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('ventas') || Schema::hasColumn('ventas', 'fecha_emision_sunat')) {
                return;
            }

            Schema::table('ventas', function (Blueprint $table): void {
                $table->date('fecha_emision_sunat')->nullable()->after('tipo_comprobante_sunat');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('ventas') || ! Schema::hasColumn('ventas', 'fecha_emision_sunat')) {
                return;
            }

            Schema::table('ventas', function (Blueprint $table): void {
                $table->dropColumn('fecha_emision_sunat');
            });
        });
    }
};
