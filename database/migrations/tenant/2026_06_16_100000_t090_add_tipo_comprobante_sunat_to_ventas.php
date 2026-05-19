<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('ventas')) {
                return;
            }

            if (Schema::hasColumn('ventas', 'tipo_comprobante_sunat')) {
                return;
            }

            Schema::table('ventas', function (Blueprint $table): void {
                $table->unsignedTinyInteger('tipo_comprobante_sunat')
                    ->nullable()
                    ->after('fel_estado')
                    ->comment('1=factura, 2=boleta (elección en caja)');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('ventas') && Schema::hasColumn('ventas', 'tipo_comprobante_sunat')) {
                Schema::table('ventas', function (Blueprint $table): void {
                    $table->dropColumn('tipo_comprobante_sunat');
                });
            }
        });
    }
};
