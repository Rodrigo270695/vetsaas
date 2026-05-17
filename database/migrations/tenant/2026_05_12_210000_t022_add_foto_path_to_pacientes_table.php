<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('pacientes', function (Blueprint $table): void {
                $table->string('foto_path', 255)->nullable()->after('nombre');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('pacientes', function (Blueprint $table): void {
                $table->dropColumn('foto_path');
            });
        });
    }
};
