<?php

declare(strict_types=1);

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paquetes de vacunación (servicio clínico → N productos) + precuenta por vacuna aplicada.
 */
return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('servicio_clinico_productos') && Schema::hasTable('servicios_clinicos')) {
                Schema::create('servicio_clinico_productos', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('servicio_clinico_id')
                        ->constrained('servicios_clinicos')
                        ->cascadeOnDelete();
                    $table->foreignUuid('producto_id')
                        ->constrained('productos')
                        ->restrictOnDelete();
                    $table->decimal('cantidad', 12, 3)->default(1);
                    $table->unsignedSmallInteger('orden')->default(0);
                    $table->timestampsTz();

                    $table->unique(['servicio_clinico_id', 'producto_id'], 'scp_servicio_producto_unique');
                    $table->index(['servicio_clinico_id', 'orden']);
                });
            }

            if (Schema::hasTable('vacunas_aplicadas')
                && ! Schema::hasColumn('vacunas_aplicadas', 'servicio_clinico_id')
            ) {
                Schema::table('vacunas_aplicadas', function (Blueprint $table): void {
                    $table->foreignUuid('servicio_clinico_id')
                        ->nullable()
                        ->after('producto_id')
                        ->constrained('servicios_clinicos')
                        ->nullOnDelete();
                });
            }

            if (Schema::hasTable('consulta_cargos')
                && ! Schema::hasColumn('consulta_cargos', 'vacuna_aplicada_id')
            ) {
                Schema::table('consulta_cargos', function (Blueprint $table): void {
                    $table->foreignUuid('vacuna_aplicada_id')
                        ->nullable()
                        ->after('hotel_estancia_id')
                        ->constrained('vacunas_aplicadas')
                        ->nullOnDelete();
                });

                DB::statement('DROP INDEX IF EXISTS consulta_cargos_vacuna_pendiente_unique');
                DB::statement('DROP INDEX IF EXISTS consulta_cargos_vacuna_aplicada_id_venta_id_index');

                DB::statement('CREATE INDEX IF NOT EXISTS consulta_cargos_vacuna_aplicada_id_venta_id_index ON consulta_cargos (vacuna_aplicada_id, venta_id)');
                DB::statement(
                    'CREATE UNIQUE INDEX IF NOT EXISTS consulta_cargos_vacuna_pendiente_unique ON consulta_cargos (vacuna_aplicada_id) WHERE vacuna_aplicada_id IS NOT NULL AND venta_id IS NULL'
                );

                // Ampliar XOR de orígenes si existe el check histórico.
                $this->refreshOrigenCheck();
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (Schema::hasTable('consulta_cargos') && Schema::hasColumn('consulta_cargos', 'vacuna_aplicada_id')) {
                DB::statement('DROP INDEX IF EXISTS consulta_cargos_vacuna_pendiente_unique');
                DB::statement('DROP INDEX IF EXISTS consulta_cargos_vacuna_aplicada_id_venta_id_index');

                Schema::table('consulta_cargos', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('vacuna_aplicada_id');
                });
            }

            if (Schema::hasTable('vacunas_aplicadas') && Schema::hasColumn('vacunas_aplicadas', 'servicio_clinico_id')) {
                Schema::table('vacunas_aplicadas', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('servicio_clinico_id');
                });
            }

            Schema::dropIfExists('servicio_clinico_productos');
        });
    }

    private function refreshOrigenCheck(): void
    {
        // Intentos de nombres históricos del check XOR.
        foreach ([
            'consulta_cargos_origen_xor',
            'consulta_cargos_check',
            'consulta_cargos_origen_check',
        ] as $name) {
            DB::statement("ALTER TABLE consulta_cargos DROP CONSTRAINT IF EXISTS {$name}");
        }

        // No recreamos un XOR rígido: multi-precuenta + nuevos orígenes lo vuelven frágil.
        // La integridad queda en FKs + unique parcial de pendiente por origen.
    }
};
