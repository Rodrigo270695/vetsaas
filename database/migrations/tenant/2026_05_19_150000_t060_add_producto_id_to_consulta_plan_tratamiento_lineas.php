<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('consulta_plan_tratamiento_lineas', function (Blueprint $table) {
                $table->foreignUuid('producto_id')
                    ->nullable()
                    ->after('plan_id')
                    ->constrained('productos')
                    ->nullOnDelete();
                $table->decimal('cantidad', 12, 3)
                    ->nullable()
                    ->after('lote');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('consulta_plan_tratamiento_lineas', function (Blueprint $table) {
                $table->dropConstrainedForeignId('producto_id');
                $table->dropColumn('cantidad');
            });
        });
    }
};
