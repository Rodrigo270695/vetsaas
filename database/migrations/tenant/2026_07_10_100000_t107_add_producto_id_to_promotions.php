<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('promotions') || Schema::hasColumn('promotions', 'producto_id')) {
                return;
            }

            Schema::table('promotions', function (Blueprint $table): void {
                $table->foreignUuid('producto_id')
                    ->nullable()
                    ->after('grooming_service_slug')
                    ->constrained('productos')
                    ->nullOnDelete();
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('promotions') || ! Schema::hasColumn('promotions', 'producto_id')) {
                return;
            }

            Schema::table('promotions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('producto_id');
            });
        });
    }
};
