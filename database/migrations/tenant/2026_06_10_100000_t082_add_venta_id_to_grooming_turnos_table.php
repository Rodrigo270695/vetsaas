<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('grooming_turnos', function (Blueprint $table): void {
                $table->foreignUuid('venta_id')
                    ->nullable()
                    ->after('updated_by_id')
                    ->constrained('ventas')
                    ->nullOnDelete();
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('grooming_turnos', function (Blueprint $table): void {
                $table->dropForeign(['venta_id']);
                $table->dropColumn('venta_id');
            });
        });
    }
};
