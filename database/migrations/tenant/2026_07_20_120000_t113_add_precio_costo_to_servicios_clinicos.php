<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('servicios_clinicos')) {
                return;
            }

            if (! Schema::hasColumn('servicios_clinicos', 'precio_costo')) {
                Schema::table('servicios_clinicos', function (Blueprint $table): void {
                    $table->decimal('precio_costo', 12, 2)->nullable()->after('precio_lista');
                });
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('servicios_clinicos') && Schema::hasColumn('servicios_clinicos', 'precio_costo')) {
                Schema::table('servicios_clinicos', function (Blueprint $table): void {
                    $table->dropColumn('precio_costo');
                });
            }
        });
    }
};
