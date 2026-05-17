<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('vacunas_aplicadas', function (Blueprint $table): void {
                $table->foreignUuid('movimiento_inventario_id')
                    ->nullable()
                    ->after('sede_id')
                    ->constrained('movimientos_inventario')
                    ->nullOnDelete();
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('vacunas_aplicadas', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('movimiento_inventario_id');
            });
        });
    }
};
