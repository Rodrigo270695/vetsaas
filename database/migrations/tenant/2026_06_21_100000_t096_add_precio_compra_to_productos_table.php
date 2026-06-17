<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('productos', function (Blueprint $table): void {
                $table->decimal('precio_compra', 10, 2)->nullable()->after('precio_venta');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('productos', function (Blueprint $table): void {
                $table->dropColumn('precio_compra');
            });
        });
    }
};
