<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasColumn('fel_documents', 'apisunat_payload')) {
                return;
            }

            Schema::table('fel_documents', function (Blueprint $table): void {
                $table->json('apisunat_payload')->nullable()->after('enlace_consulta');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasColumn('fel_documents', 'apisunat_payload')) {
                return;
            }

            Schema::table('fel_documents', function (Blueprint $table): void {
                $table->dropColumn('apisunat_payload');
            });
        });
    }
};
