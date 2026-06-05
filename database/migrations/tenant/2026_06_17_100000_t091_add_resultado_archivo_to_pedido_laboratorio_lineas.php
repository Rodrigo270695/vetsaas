<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('pedido_laboratorio_lineas', function (Blueprint $table) {
                $table->string('resultado_archivo_path', 500)->nullable()->after('resultado_at');
                $table->string('resultado_archivo_original_name', 255)->nullable()->after('resultado_archivo_path');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('pedido_laboratorio_lineas', function (Blueprint $table) {
                $table->dropColumn(['resultado_archivo_path', 'resultado_archivo_original_name']);
            });
        });
    }
};
