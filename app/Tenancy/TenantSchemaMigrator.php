<?php

namespace App\Tenancy;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Aplica `database/migrations/tenant` sobre un schema PostgreSQL concreto.
 *
 * Centraliza la lógica usada por `vetsaas:tenant-migrate` y por el comando
 * masivo `vetsaas:tenant-migrate-all` para no duplicar el flujo (CREATE SCHEMA,
 * wipe/replay opcional, `tenant.migration_schema`, `migrate --path=...`).
 *
 * Importante multi-tenant: el log de migraciones debe vivir **por schema**
 * (tabla `migrations` dentro del tenant) y el `search_path` debe seguir
 * poniendo el tenant delante de `public` entre pasos del migrador. Ver
 * {@see TenantMigration} y el bootstrap aquí para no contaminar
 * `public.migrations` (que haría que otros tenants vean "Nothing to migrate").
 */
class TenantSchemaMigrator
{
    /**
     * @return self::EXIT_* constantes alineadas con Symfony Command
     */
    public const EXIT_SUCCESS = 0;

    public const EXIT_FAILURE = 1;

    /**
     * Ejecuta migraciones tenant en el schema indicado.
     *
     * @param  bool  $wipe  DROP SCHEMA + recrear vacío (destructivo).
     * @param  bool  $replay  Borra filas del log de migraciones tenant (en el
     *                        schema del tenant y en `public.migrations` como
     *                        limpieza) y vuelve a migrar (solo desarrollo /
     *                        schemas controlados).
     */
    public function migrate(
        string $schema,
        OutputInterface $output,
        bool $wipe = false,
        bool $replay = false,
    ): int {
        if (! preg_match('/^[a-z_][a-z0-9_]{0,62}$/i', $schema)) {
            $output->writeln('<error>Nombre de schema inválido: '.$schema.'</error>');

            return self::EXIT_FAILURE;
        }

        if (DB::getDriverName() !== 'pgsql') {
            $output->writeln('<error>Solo está soportado PostgreSQL para multi-schema tenant.</error>');

            return self::EXIT_FAILURE;
        }

        $safe = str_replace('"', '', $schema);

        DB::statement('CREATE SCHEMA IF NOT EXISTS "'.$safe.'"');

        if ($wipe) {
            DB::statement('DROP SCHEMA IF EXISTS "'.$safe.'" CASCADE');
            DB::statement('CREATE SCHEMA "'.$safe.'"');
            $output->writeln('<info>Schema recreado vacío (wipe): '.$safe.'</info>');
        }

        $tenantMigrationNames = $this->tenantMigrationBasenames();

        if (($replay || $wipe) && $tenantMigrationNames !== []) {
            foreach ($tenantMigrationNames as $name) {
                DB::delete('delete from public.migrations where migration = ?', [$name]);
            }
            DB::statement('SET search_path TO "'.$safe.'", public');
            try {
                if (Schema::hasTable('migrations')) {
                    DB::table('migrations')->whereIn('migration', $tenantMigrationNames)->delete();
                }
            } finally {
                DB::statement('SET search_path TO public');
            }
            $output->writeln('<info>Historial de migraciones tenant reiniciado (replay/wipe).</info>');
        }

        config(['tenant.migration_schema' => $schema]);

        DB::statement('SET search_path TO "'.$safe.'", public');

        try {
            $this->ensureTenantMigrationsTableExists();
            $this->bootstrapMigrationRowsFromExistingTables($output);

            $exitCode = Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
            $output->write(Artisan::output());

            if ($exitCode !== 0) {
                $output->writeln('<error>migrate terminó con código '.$exitCode.'</error>');

                return self::EXIT_FAILURE;
            }

            $this->purgePublicTenantMigrationRows($tenantMigrationNames);
        } catch (\Throwable $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');
            $previous = $e->getPrevious();
            if ($previous instanceof \Throwable) {
                $output->writeln('<error>Causa: '.$previous->getMessage().'</error>');
            }

            return self::EXIT_FAILURE;
        } finally {
            $this->resetConnectionAfterMigrate();
            config(['tenant.migration_schema' => null]);
        }

        $output->writeln('<info>Schema listo: '.$safe.'</info>');

        return self::EXIT_SUCCESS;
    }

    /**
     * @return list<string> nombres de archivo sin `.php`, ordenados
     */
    private function tenantMigrationBasenames(): array
    {
        $paths = glob(database_path('migrations/tenant/*.php')) ?: [];

        return collect($paths)
            ->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
            ->sort()
            ->values()
            ->all();
    }

    private function ensureTenantMigrationsTableExists(): void
    {
        if (Schema::hasTable('migrations')) {
            return;
        }

        Schema::create('migrations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });
    }

    /**
     * Compatibilidad con despliegues previos: el log quedó en `public.migrations`
     * y otros schemas no recibieron DDL nuevo. Marca como aplicadas las
     * migraciones cuyas tablas/columnas ya existen en este schema.
     */
    private function bootstrapMigrationRowsFromExistingTables(OutputInterface $output): void
    {
        $names = $this->tenantMigrationBasenames();
        if ($names === []) {
            return;
        }

        $batch = max(1, (int) DB::table('migrations')->max('batch'));
        $inserted = 0;

        foreach ($names as $migration) {
            if (DB::table('migrations')->where('migration', $migration)->exists()) {
                continue;
            }

            if (! $this->tenantMigrationIsMaterialized($migration)) {
                continue;
            }

            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => $batch,
            ]);
            $inserted++;
        }

        if ($inserted > 0) {
            $output->writeln('<info>Bootstrap: '.$inserted.' migración(es) tenant marcadas como ya aplicadas en este schema (estado legado).</info>');
        }
    }

    private function tenantMigrationIsMaterialized(string $migration): bool
    {
        return match ($migration) {
            '2026_05_12_080100_t010_create_cfg_clinic_settings_table' => Schema::hasTable('cfg_clinic_settings'),
            '2026_05_12_200000_t020_create_propietarios_table' => Schema::hasTable('propietarios'),
            '2026_05_12_200100_t021_create_pacientes_table' => Schema::hasTable('pacientes'),
            '2026_05_12_210000_t022_add_foto_path_to_pacientes_table' => Schema::hasTable('pacientes')
                && Schema::hasColumn('pacientes', 'foto_path'),
            '2026_05_12_220000_t023_create_historias_clinicas_table' => Schema::hasTable('historias_clinicas'),
            '2026_05_12_220050_t050_create_categorias_productos_table' => Schema::hasTable('categorias_productos'),
            '2026_05_12_220075_t052_create_productos_table' => Schema::hasTable('productos'),
            '2026_05_13_120000_t053_create_unidades_medida_table' => Schema::hasTable('unidades_medida'),
            '2026_05_14_210000_t054_create_existencias_sede_table' => Schema::hasTable('existencias_sede'),
            '2026_05_15_100000_t055_create_movimientos_inventario_table' => Schema::hasTable('movimientos_inventario'),
            '2026_05_16_100000_t056_add_stock_minimo_to_productos_table' => Schema::hasTable('productos')
                && Schema::hasColumn('productos', 'stock_minimo'),
            '2026_05_17_120000_t057_create_proveedores_table' => Schema::hasTable('proveedores'),
            '2026_05_18_100000_t058_compras_inventario' => Schema::hasTable('compras')
                && Schema::hasTable('compra_lineas')
                && Schema::hasColumn('movimientos_inventario', 'compra_id'),
            '2026_05_19_140000_t059_add_anulada_to_compras_table' => Schema::hasTable('compras')
                && Schema::hasColumn('compras', 'anulada_at')
                && Schema::hasColumn('compras', 'anulada_por_id'),
            '2026_05_12_220100_t024_create_consultas_table' => Schema::hasTable('consultas'),
            '2026_05_12_230000_t025_create_consulta_plan_tratamiento_tables' => Schema::hasTable('consulta_planes_tratamiento')
                && Schema::hasTable('consulta_plan_tratamiento_lineas'),
            '2026_05_13_120000_add_anadido_en_to_consulta_plan_tratamiento_lineas' => Schema::hasTable('consulta_plan_tratamiento_lineas')
                && Schema::hasColumn('consulta_plan_tratamiento_lineas', 'anadido_en'),
            '2026_05_19_150000_t060_add_producto_id_to_consulta_plan_tratamiento_lineas' => Schema::hasTable('consulta_plan_tratamiento_lineas')
                && Schema::hasColumn('consulta_plan_tratamiento_lineas', 'producto_id'),
            '2026_05_20_100000_t061_create_vacunas_aplicadas_table' => Schema::hasTable('vacunas_aplicadas'),
            '2026_05_21_120000_t062_add_movimiento_inventario_id_to_vacunas_aplicadas' => Schema::hasTable('vacunas_aplicadas')
                && Schema::hasColumn('vacunas_aplicadas', 'movimiento_inventario_id'),
            '2026_05_22_100000_t063_add_clinical_fields_to_vacunas_aplicadas' => Schema::hasTable('vacunas_aplicadas')
                && Schema::hasColumn('vacunas_aplicadas', 'categoria_registro'),
            '2026_05_23_120000_t064_consulta_vitales_cierre_vacuna_consulta' => Schema::hasTable('consultas')
                && Schema::hasColumn('consultas', 'cerrada_at')
                && Schema::hasColumn('vacunas_aplicadas', 'consulta_id'),
            '2026_05_24_100000_t065_create_citas_table' => Schema::hasTable('citas'),
            '2026_05_25_100000_t066_create_recetas_tables' => Schema::hasTable('recetas')
                && Schema::hasTable('receta_lineas'),
            '2026_05_26_100000_t067_create_pedidos_laboratorio_tables' => Schema::hasTable('pedidos_laboratorio')
                && Schema::hasTable('pedido_laboratorio_lineas'),
            '2026_05_27_100000_t068_create_cirugias_table' => Schema::hasTable('cirugias'),
            '2026_05_28_100000_t069_create_consulta_cargo_tables' => Schema::hasTable('consulta_cargos')
                && Schema::hasTable('consulta_cargo_lineas'),
            '2026_05_29_100000_t070_add_ticket_ancho_mm_to_cfg_clinic_settings' => Schema::hasTable('cfg_clinic_settings')
                && Schema::hasColumn('cfg_clinic_settings', 'ticket_ancho_mm'),
            '2026_05_30_100000_t071_create_caja_sesiones_table' => Schema::hasTable('caja_sesiones'),
            '2026_05_31_100000_t072_add_emite_comprobantes_sunat_to_cfg_clinic_settings' => Schema::hasTable('cfg_clinic_settings')
                && Schema::hasColumn('cfg_clinic_settings', 'emite_comprobantes_sunat'),
            '2026_06_01_100000_t073_create_ventas_tables' => Schema::hasTable('ventas')
                && Schema::hasTable('venta_lineas')
                && Schema::hasColumn('movimientos_inventario', 'venta_id'),
            '2026_06_02_100000_t074_ventas_consulta_vinculo' => Schema::hasTable('ventas')
                && Schema::hasColumn('ventas', 'consulta_id')
                && Schema::hasColumn('venta_lineas', 'tipo_linea'),
            '2026_06_03_100000_t075_create_fel_tables' => Schema::hasTable('fel_documents')
                && Schema::hasTable('fel_series'),
            '2026_06_04_100000_t076_create_internamientos_table' => Schema::hasTable('internamientos'),
            '2026_06_05_100000_t077_create_internamiento_evoluciones_table' => Schema::hasTable('internamiento_evoluciones'),
            '2026_06_06_100000_t078_consulta_cargos_internamiento' => Schema::hasTable('consulta_cargos')
                && Schema::hasColumn('consulta_cargos', 'internamiento_id'),
            '2026_06_07_100000_t079_create_grooming_turnos_table' => Schema::hasTable('grooming_turnos'),
            '2026_06_08_100000_t080_add_servicio_detalle_to_grooming_turnos_table' => Schema::hasTable('grooming_turnos')
                && Schema::hasColumn('grooming_turnos', 'servicio_detalle'),
            '2026_06_09_100000_t081_consulta_cargos_grooming_turno' => Schema::hasTable('consulta_cargos')
                && Schema::hasColumn('consulta_cargos', 'grooming_turno_id'),
            '2026_06_10_100000_t082_add_venta_id_to_grooming_turnos_table' => Schema::hasTable('grooming_turnos')
                && Schema::hasColumn('grooming_turnos', 'venta_id'),
            '2026_06_11_100000_t083_create_grooming_servicio_tarifas_table' => Schema::hasTable('grooming_servicio_tarifas'),
            '2026_06_12_100000_t084_create_hotel_estancias_table' => Schema::hasTable('hotel_estancias'),
            '2026_06_12_110000_t085_create_hotel_estancia_diarios_table' => Schema::hasTable('hotel_estancia_diarios'),
            '2026_06_12_120000_t086_consulta_cargos_hotel_estancia' => Schema::hasTable('consulta_cargos')
                && Schema::hasColumn('consulta_cargos', 'hotel_estancia_id'),
            '2026_06_13_100000_t087_create_hotel_estancia_tarifas_table' => Schema::hasTable('hotel_estancia_tarifas'),
            '2026_06_14_100000_t088_ventas_anulacion_and_fel_anulado' => Schema::hasTable('ventas')
                && Schema::hasColumn('ventas', 'anulado_at'),
            '2026_06_15_100000_t089_add_nubefact_api_ruta_to_cfg_clinic_settings' => Schema::hasTable('cfg_clinic_settings')
                && Schema::hasColumn('cfg_clinic_settings', 'nubefact_api_ruta'),
            '2026_06_15_200000_t090_add_apisunat_to_cfg_clinic_settings' => Schema::hasTable('cfg_clinic_settings')
                && Schema::hasColumn('cfg_clinic_settings', 'apisunat_token_enc')
                && Schema::hasColumn('cfg_clinic_settings', 'apisunat_mode')
                && Schema::hasColumn('cfg_clinic_settings', 'apisunat_configurado'),
            '2026_06_16_100000_t090_add_tipo_comprobante_sunat_to_ventas' => Schema::hasTable('ventas')
                && Schema::hasColumn('ventas', 'tipo_comprobante_sunat'),
            '2026_06_17_100000_t091_add_resultado_archivo_to_pedido_laboratorio_lineas' => Schema::hasTable('pedido_laboratorio_lineas')
                && Schema::hasColumn('pedido_laboratorio_lineas', 'resultado_archivo_path'),
            '2026_06_18_100000_t092_create_notifications_queue_table' => Schema::hasTable('notifications_queue'),
            '2026_06_18_120000_t093_add_apisunat_payload_to_fel_documents' => Schema::hasTable('fel_documents')
                && Schema::hasColumn('fel_documents', 'apisunat_payload'),
            '2026_06_19_100000_t094_grooming_servicios_personalizados' => Schema::hasTable('grooming_servicios')
                && Schema::hasColumn('cfg_clinic_settings', 'grooming_catalogo_personalizado'),
            '2026_06_20_100000_t095_clinic_catalogos_por_clinica' => Schema::hasTable('hotel_tipos_estancia')
                && Schema::hasColumn('cfg_clinic_settings', 'hotel_catalogo_personalizado'),
            '2026_06_21_100000_t096_add_precio_compra_to_productos_table' => Schema::hasTable('productos')
                && Schema::hasColumn('productos', 'precio_compra'),
            '2026_06_22_100000_t097_create_promotions_table' => Schema::hasTable('promotions'),
            '2026_06_23_100000_t098_add_apisunat_mode_to_fel_documents' => Schema::hasTable('fel_documents')
                && Schema::hasColumn('fel_documents', 'apisunat_mode'),
            '2026_06_24_100000_t099_backfill_apisunat_mode_on_fel_documents' => false,
            '2026_06_25_100000_t100_propietarios_unique_documento_per_tenant' => false,
            '2026_07_11_100000_t108_create_producto_lotes' => Schema::hasTable('producto_lotes')
                && Schema::hasColumn('movimientos_inventario', 'producto_lote_id')
                && Schema::hasColumn('consulta_plan_tratamiento_lineas', 'movimiento_inventario_id'),
            default => false,
        };
    }

    private function resetConnectionAfterMigrate(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        try {
            DB::statement('ROLLBACK');
        } catch (\Throwable) {
            // Sin transacción abierta en el servidor.
        }

        DB::reconnect();
        DB::statement('SET search_path TO public');
    }

    /**
     * Evita que filas huérfanas en `public.migrations` sigan afectando otros
     * flujos o herramientas que lean la tabla global.
     *
     * @param  list<string>  $tenantMigrationNames
     */
    private function purgePublicTenantMigrationRows(array $tenantMigrationNames): void
    {
        if ($tenantMigrationNames === []) {
            return;
        }

        foreach ($tenantMigrationNames as $name) {
            DB::delete('delete from public.migrations where migration = ?', [$name]);
        }
    }
}
