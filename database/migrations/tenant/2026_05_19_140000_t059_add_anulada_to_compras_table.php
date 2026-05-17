<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('compras', function (Blueprint $table) {
                $table->timestampTz('anulada_at')->nullable()->after('updated_by_id');
                $table->foreignUuid('anulada_por_id')
                    ->nullable()
                    ->after('anulada_at')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('compras', function (Blueprint $table) {
                $table->dropForeign(['anulada_por_id']);
                $table->dropColumn(['anulada_at', 'anulada_por_id']);
            });
        });
    }
};
