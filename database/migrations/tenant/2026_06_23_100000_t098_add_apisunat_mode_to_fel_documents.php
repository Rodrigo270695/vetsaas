<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('fel_documents', function (Blueprint $table): void {
                if (! Schema::hasColumn('fel_documents', 'apisunat_mode')) {
                    $table->string('apisunat_mode', 20)->nullable()->after('apisunat_payload');
                }
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('fel_documents', function (Blueprint $table): void {
                if (Schema::hasColumn('fel_documents', 'apisunat_mode')) {
                    $table->dropColumn('apisunat_mode');
                }
            });
        });
    }
};
