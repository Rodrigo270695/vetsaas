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
                $table->decimal('stock_minimo', 12, 3)->nullable()->after('medicamento');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('productos', function (Blueprint $table): void {
                $table->dropColumn('stock_minimo');
            });
        });
    }
};
