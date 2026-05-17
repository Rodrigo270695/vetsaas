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
                $table->date('anadido_en')->nullable()->after('notas');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('consulta_plan_tratamiento_lineas', function (Blueprint $table) {
                $table->dropColumn('anadido_en');
            });
        });
    }
};
