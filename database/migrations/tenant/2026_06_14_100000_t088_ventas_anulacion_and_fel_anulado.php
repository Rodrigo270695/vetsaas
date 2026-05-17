<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('ventas', function (Blueprint $table): void {
                $table->timestampTz('anulado_at')->nullable()->after('fecha_pago');
                $table->uuid('anulado_por_id')->nullable()->after('anulado_at');
                $table->text('motivo_anulacion')->nullable()->after('anulado_por_id');

                $table->index('anulado_at');
            });

            Schema::table('fel_documents', function (Blueprint $table): void {
                $table->timestampTz('anulado_at')->nullable()->after('emitido_at');
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::table('fel_documents', function (Blueprint $table): void {
                $table->dropColumn('anulado_at');
            });

            Schema::table('ventas', function (Blueprint $table): void {
                $table->dropIndex(['anulado_at']);
                $table->dropColumn(['anulado_at', 'anulado_por_id', 'motivo_anulacion']);
            });
        });
    }
};
