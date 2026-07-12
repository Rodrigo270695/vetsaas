<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('movimientos_inventario', function (Blueprint $table) {
                $table->uuid('fefo_grupo_id')->nullable()->after('producto_lote_id');
                $table->index('fefo_grupo_id');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('movimientos_inventario', function (Blueprint $table) {
                $table->dropIndex(['fefo_grupo_id']);
                $table->dropColumn('fefo_grupo_id');
            });
        });
    }
};
