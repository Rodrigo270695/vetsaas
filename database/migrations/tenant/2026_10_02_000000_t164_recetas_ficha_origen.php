<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amarra una receta a la ficha de desparasitación o de antipulgas.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('recetas')) {
                return;
            }

            Schema::table('recetas', function (Blueprint $table): void {
                if (Schema::hasTable('desparasitaciones') && ! Schema::hasColumn('recetas', 'desparasitacion_id')) {
                    $table->foreignUuid('desparasitacion_id')
                        ->nullable()
                        ->constrained('desparasitaciones')
                        ->nullOnDelete();
                }
                if (Schema::hasTable('antipulgas') && ! Schema::hasColumn('recetas', 'antipulga_id')) {
                    $table->foreignUuid('antipulga_id')
                        ->nullable()
                        ->constrained('antipulgas')
                        ->nullOnDelete();
                }
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('recetas')) {
                return;
            }

            Schema::table('recetas', function (Blueprint $table): void {
                if (Schema::hasColumn('recetas', 'desparasitacion_id')) {
                    $table->dropConstrainedForeignId('desparasitacion_id');
                }
                if (Schema::hasColumn('recetas', 'antipulga_id')) {
                    $table->dropConstrainedForeignId('antipulga_id');
                }
            });
        });
    }
};
