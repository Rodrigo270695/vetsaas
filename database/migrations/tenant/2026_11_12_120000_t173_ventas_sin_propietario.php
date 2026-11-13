<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('ventas') || Schema::hasColumn('ventas', 'cliente_nombre')) {
                return;
            }

            DB::statement('ALTER TABLE ventas ALTER COLUMN propietario_id DROP NOT NULL');

            Schema::table('ventas', function (Blueprint $table): void {
                $table->string('cliente_nombre', 200)->nullable()->after('propietario_id');
                $table->string('cliente_documento', 15)->nullable()->after('cliente_nombre');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('ventas') || ! Schema::hasColumn('ventas', 'cliente_nombre')) {
                return;
            }

            Schema::table('ventas', function (Blueprint $table): void {
                $table->dropColumn(['cliente_nombre', 'cliente_documento']);
            });

            DB::statement('ALTER TABLE ventas ALTER COLUMN propietario_id SET NOT NULL');
        });
    }
};
