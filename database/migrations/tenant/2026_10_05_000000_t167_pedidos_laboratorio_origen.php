<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El examen subido desde una ficha (defunción, desparasitación, etc.)
 * queda ligado a esa ficha y no abre un laboratorio suelto.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('pedidos_laboratorio')) {
                return;
            }

            Schema::table('pedidos_laboratorio', function (Blueprint $table): void {
                if (! Schema::hasColumn('pedidos_laboratorio', 'origen_kind')) {
                    $table->string('origen_kind', 40)->nullable()->after('consulta_id');
                }
                if (! Schema::hasColumn('pedidos_laboratorio', 'origen_id')) {
                    $table->uuid('origen_id')->nullable()->after('origen_kind');
                    $table->index(['origen_kind', 'origen_id'], 'pedidos_lab_origen_idx');
                }
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('pedidos_laboratorio')) {
                return;
            }

            Schema::table('pedidos_laboratorio', function (Blueprint $table): void {
                if (Schema::hasColumn('pedidos_laboratorio', 'origen_id')) {
                    $table->dropIndex('pedidos_lab_origen_idx');
                    $table->dropColumn('origen_id');
                }
                if (Schema::hasColumn('pedidos_laboratorio', 'origen_kind')) {
                    $table->dropColumn('origen_kind');
                }
            });
        });
    }
};
