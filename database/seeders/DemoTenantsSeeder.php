<?php

namespace Database\Seeders;

use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Pobla la base de datos con tenants de demostración listos para usar.
 *
 * Pensado para desarrollo: tras un `migrate:fresh --seed` o un nuevo
 * setup, este seeder te deja 3 clínicas funcionales con sus admins.
 * Idempotente: lo puedes volver a correr y se actualizan los registros
 * existentes (no duplica).
 *
 * Lo que crea por cada tenant:
 *
 *   1. Fila en `public.tenants` (slug, schema_name, estado, locale...).
 *   2. Schema físico en Postgres + migraciones tenant aplicadas.
 *   3. Usuario admin en `public.users` con rol `admin_clinica`.
 *      Password fija (`clave123456`) y `must_change_password=false` para
 *      poder loguear directo. NO usar en producción.
 *   4. Fila en `cfg_clinic_settings` del schema con datos demo
 *      (RUC, razón social, colores) — útil para que la pantalla de
 *      Configuración › General no se vea vacía al primer login.
 *
 * Por defecto NO se invoca desde `DatabaseSeeder` (queremos mantener
 * la separación entre "infraestructura mínima" y "data de demo"). Se
 * dispara explícitamente con:
 *
 *     php artisan db:seed --class=DemoTenantsSeeder
 *
 * O, recomendado en desarrollo local:
 *
 *     php artisan migrate --seed
 *     php artisan db:seed --class=DemoTenantsSeeder
 *
 * En production NUNCA uses wipe global: solo `vetsaas:reset-demo --rebuild`.
 */
class DemoTenantsSeeder extends Seeder
{
    /**
     * Catálogo de tenants demo. Editar aquí para añadir/quitar clínicas.
     *
     * Cada entrada se traduce en:
     *   · subdomain  → http://<slug>.localhost:8000
     *   · login      → admin@<slug>.test  (o email_override si se define)
     *   · password   → clave123456 (fija, sin force-change)
     *
     * Claves opcionales por entrada:
     *   · email_override  → email del usuario admin (sustituye admin@<slug>.test)
     *   · password_override → contraseña del usuario admin
     *
     * @var list<array{slug:string,nombre_comercial:string,razon_social:string,ruc:string,color_primario:string,color_secundario:string,email_override?:string,password_override?:string}>
     */
    private const TENANTS = [
        /*
         * ── Tenant público de demostración ────────────────────────────────
         * Credenciales que se publican en WhatsApp / Facebook Ads:
         *   Usuario : demo@vetsaas.pe
         *   Clave   : demo1234
         * Plan PRO para mostrar todos los módulos al prospecto.
         * Sus datos son recargados periódicamente por DemoDataSeeder.
         */
        /*
         * ── Único tenant público de demostración ──────────────────────────
         * Credenciales que se publican en WhatsApp / Facebook Ads:
         *   Usuario : demo@vetsaas.pe
         *   Clave   : demo1234
         * Plan PRO para mostrar todos los módulos al prospecto.
         * Sus datos son recargados periódicamente por DemoDataSeeder
         * (comando: php artisan vetsaas:reset-demo)
         */
        [
            'slug'              => 'demo',
            'nombre_comercial'  => 'Clínica Veterinaria Demo',
            'razon_social'      => 'VetSaaS Demo SAC',
            'ruc'               => '20999999999',
            'color_primario'    => '#1F6F43',
            'color_secundario'  => '#94C7A8',
            'email_override'    => 'demo@vetsaas.pe',
            'password_override' => 'demo1234',
        ],
    ];

    private const DEMO_PASSWORD = 'clave123456';

    public function run(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->command?->warn('DemoTenantsSeeder requiere PostgreSQL (multi-schema). Omitido.');

            return;
        }

        if (app()->isProduction()) {
            foreach (self::TENANTS as $config) {
                if (($config['slug'] ?? '') !== 'demo') {
                    $this->command?->error('DemoTenantsSeeder en production solo admite el slug demo. Abortado.');

                    return;
                }
            }
        }

        $schemaPrefix = (string) config('tenant.schema_prefix', 'vet_');

        foreach (self::TENANTS as $config) {
            $schemaName = $schemaPrefix.str_replace('-', '_', $config['slug']);

            $tenant = $this->ensureTenant($config, $schemaName);
            $this->ensureSchema($schemaName);
            $this->ensureAdmin($tenant, $config);
            $this->ensureClinicSettings($schemaName, $config);
            $invSeeder = new InventarioCategoriasYProductosSeeder;
            if ($this->command !== null) {
                $invSeeder->setCommand($this->command);
            }
            $invSeeder->seedForSchema($schemaName, $config['slug']);
            $this->ensurePrimarySede($tenant);

            $loginEmail = (string) ($config['email_override'] ?? ('admin@'.$config['slug'].'.test'));
            $loginPass  = (string) ($config['password_override'] ?? self::DEMO_PASSWORD);

            $this->command?->info(sprintf(
                '✓ %s — http://%s.localhost:8000/login  (%s / %s)',
                $config['nombre_comercial'],
                $config['slug'],
                $loginEmail,
                $loginPass,
            ));
        }
    }

    /**
     * Una sola sede activa por tenant demo. No invoca SedesSeeder global
     * (ese seeder toca TODOS los tenants de public y choca con soft-deletes).
     */
    private function ensurePrimarySede(Tenant $tenant): void
    {
        Sede::withTrashed()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'codigo' => 'LIM-01',
            ],
            [
                'nombre' => 'Sede Lima Centro',
                'direccion' => 'Av. Arequipa 1234',
                'distrito' => 'Lince',
                'provincia' => 'Lima',
                'departamento' => 'Lima',
                'telefono' => '+51 1 555-0101',
                'email' => 'lima@vetsaas.pe',
                'serie_factura' => 'F001',
                'serie_boleta' => 'B001',
                'activa' => true,
                'deleted_at' => null,
            ],
        );
    }

    /**
     * Crea o actualiza la fila en `public.tenants`. Idempotente: si ya
     * existe (por `slug`), se actualizan los datos visibles.
     *
     * @param  array<string,mixed>  $config
     */
    private function ensureTenant(array $config, string $schemaName): Tenant
    {
        return Tenant::query()->updateOrCreate(
            ['slug' => $config['slug']],
            [
                'schema_name' => $schemaName,
                'razon_social' => $config['razon_social'],
                'nombre_comercial' => $config['nombre_comercial'],
                'email_admin' => 'admin@'.$config['slug'].'.test',
                'timezone' => 'America/Lima',
                'locale' => 'es',
                'estado' => 'active',
            ],
        );
    }

    /**
     * Asegura que el schema físico existe en Postgres y que tiene
     * aplicadas las migraciones tenant. Usa `--replay` para que sea
     * idempotente incluso si las columnas cambian entre corridas
     * (típico mientras se desarrolla un módulo nuevo).
     *
     * Drop + recreate del schema es seguro porque el seeder está
     * pensado para datos de demo, no para producción.
     */
    private function ensureSchema(string $schemaName): void
    {
        // Drop + recreate: garantizamos que la estructura está alineada
        // con la última versión de las migraciones tenant. Si el schema
        // tenía data demo previa, se reemplaza con la nueva (es lo que
        // queremos al correr el seeder de nuevo).
        DB::statement('DROP SCHEMA IF EXISTS "'.$schemaName.'" CASCADE');

        Artisan::call('vetsaas:tenant-migrate', [
            'schema' => $schemaName,
            '--replay' => true,
        ]);
    }

    /**
     * Crea o actualiza el usuario admin del tenant con rol
     * `admin_clinica`. Password fija para facilitar login en demo
     * (`clave123456`) y `must_change_password=false` para no forzar
     * el flujo de cambio en el primer login.
     *
     * Si la entrada tiene `email_override` o `password_override`, se
     * usan esos valores en lugar de los predeterminados. Esto permite
     * exponer credenciales públicas para el tenant de demostración.
     *
     * @param  array<string,mixed>  $config
     */
    private function ensureAdmin(Tenant $tenant, array $config): void
    {
        $email    = (string) ($config['email_override'] ?? ('admin@'.$config['slug'].'.test'));
        $password = (string) ($config['password_override'] ?? self::DEMO_PASSWORD);

        (new TenantRolesSeeder)->seedForTenant((string) $tenant->id);

        $user = User::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'email' => $email],
            [
                'name' => 'Admin '.$config['nombre_comercial'],
                'password' => Hash::make($password),
                'is_active' => true,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ],
        );

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId((string) $tenant->id);
        try {
            $user->syncRoles(['admin_clinica']);
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }

    /**
     * Pre-llena la única fila de `cfg_clinic_settings` del schema con
     * datos demo (RUC, razón social, colores). Sin esto, la primera
     * carga de `/configuracion/general` autoprovisionaría una fila con
     * todos los campos en blanco, lo cual da una impresión peor en
     * presentaciones.
     *
     * @param  array<string,mixed>  $config
     */
    private function ensureClinicSettings(string $schemaName, array $config): void
    {
        // Cambiamos temporalmente el search_path al schema del tenant
        // para que el insert toque su tabla cfg_clinic_settings (vive
        // dentro del schema, no en public).
        DB::statement('SET search_path TO "'.$schemaName.'", public');

        try {
            $now = now();

            DB::table('cfg_clinic_settings')->updateOrInsert(
                [], // singleton: la única fila o la creamos
                [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'ruc' => $config['ruc'],
                    'razon_social' => $config['razon_social'],
                    'nombre_comercial' => $config['nombre_comercial'],
                    'direccion_fiscal' => 'Av. Demo 123, Lima',
                    'color_primario' => $config['color_primario'],
                    'color_secundario' => $config['color_secundario'],
                    'email_institucional' => 'contacto@'.$config['slug'].'.test',
                    'telefono_principal' => '+51 1 555-0100',
                    'web_url' => 'https://'.$config['slug'].'.test',
                    'duracion_cita_default_min' => 30,
                    'intervalo_agenda_min' => 15,
                    'horario_atencion' => '{}',
                    'dias_anticipacion_cita' => 30,
                    'recordatorio_48h_activo' => true,
                    'recordatorio_2h_activo' => true,
                    'recordatorio_vacuna_activo' => true,
                    'recordatorio_vacuna_dias_antes' => 7,
                    'recordatorio_cumple_activo' => false,
                    'nubefact_configurado' => false,
                    'moneda' => 'PEN',
                    'igv_porcentaje' => 18.00,
                    'precio_incluye_igv' => true,
                    'horas_min_cancelacion' => 24,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        } finally {
            DB::statement('SET search_path TO public');
        }
    }
}
