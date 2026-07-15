<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\InventarioCategoriasYProductosSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Resetea el tenant slug `demo` (datos + contraseña).
 *
 * Uso normal (scheduler 03:00):
 *   php artisan vetsaas:reset-demo
 *
 * Rebuild completo del schema demo (si quedó roto / sin permisos / vacío):
 *   php artisan vetsaas:reset-demo --rebuild
 *
 * No toca otras clínicas.
 */
final class ResetDemoCommand extends Command
{
    protected $signature = 'vetsaas:reset-demo
                            {--rebuild : DROP + migrar schema vet_demo, roles admin y datos desde cero}';

    protected $description = 'Resetea datos y contraseña del tenant demo (corre automáticamente cada noche)';

    private const DEMO_SLUG = 'demo';

    private const DEMO_EMAIL = 'demo@vetsaas.pe';

    private const DEMO_PASSWORD = 'demo1234';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Este comando requiere PostgreSQL.');

            return self::FAILURE;
        }

        $this->info('── Reset demo ─────────────────────────────────');

        $tenant = Tenant::query()->where('slug', self::DEMO_SLUG)->first();

        if ($tenant === null) {
            $this->error('Tenant "demo" no encontrado en public.tenants.');
            $this->line('Crea la fila demo o ejecuta: php artisan db:seed --class=DemoTenantsSeeder --force');

            return self::FAILURE;
        }

        $schema = (string) $tenant->schema_name;

        if ($this->option('rebuild')) {
            $this->warn("Rebuild completo del schema {$schema} (solo demo)…");
            $ok = $this->rebuildSchema($tenant, $schema);
            if (! $ok) {
                return self::FAILURE;
            }
        } else {
            $exists = (bool) DB::selectOne(
                'select exists(select 1 from information_schema.schemata where schema_name = ?) as ok',
                [$schema],
            )?->ok;

            if (! $exists) {
                $this->error("El schema {$schema} no existe. Ejecuta: php artisan vetsaas:reset-demo --rebuild");

                return self::FAILURE;
            }
        }

        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', self::DEMO_EMAIL)
            ->first();

        if ($user !== null) {
            $user->password = Hash::make(self::DEMO_PASSWORD);
            $user->must_change_password = false;
            $user->is_active = true;
            $user->save();
            $user->syncRoles(['admin_clinica']);
            $this->line('  → Usuario demo@vetsaas.pe: clave demo1234 + rol admin_clinica.');
        } else {
            $this->warn('  ⚠ Usuario demo@vetsaas.pe no encontrado — recreando…');
            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'email' => self::DEMO_EMAIL,
                'name' => 'Admin Clínica Veterinaria Demo',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'is_active' => true,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles(['admin_clinica']);
        }

        $this->ensureDemoSede($tenant);

        $this->line('  → Recargando datos clínicos…');
        $seeder = new DemoDataSeeder;
        $seeder->setCommand($this);
        $seeder->run();

        $this->info('✓ Demo listo — https://demo.'.config('tenant.root_domain', 'vetsaas.orvae.pe').'/login');
        $this->line('  demo@vetsaas.pe / demo1234');

        return self::SUCCESS;
    }

    private function rebuildSchema(Tenant $tenant, string $schema): bool
    {
        try {
            DB::statement('DROP SCHEMA IF EXISTS "'.$schema.'" CASCADE');
            $exit = Artisan::call('vetsaas:tenant-migrate', [
                'schema' => $schema,
                '--replay' => true,
            ], $this->output);

            if ($exit !== self::SUCCESS) {
                $this->error('Falló vetsaas:tenant-migrate en '.$schema);

                return false;
            }

            $inv = new InventarioCategoriasYProductosSeeder;
            $inv->setCommand($this);
            $inv->seedForSchema($schema, self::DEMO_SLUG);

            $this->seedClinicSettings($schema);
            $tenant->refresh();
            $this->line("  → Schema {$schema} recreado + inventario base.");

            return true;
        } catch (\Throwable $e) {
            $this->error('Rebuild falló: '.$e->getMessage());

            return false;
        }
    }

    private function ensureDemoSede(Tenant $tenant): void
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

        $this->line('  → Sede LIM-01 asegurada.');
    }

    private function seedClinicSettings(string $schema): void
    {
        DB::statement('SET search_path TO "'.$schema.'", public');

        try {
            $now = now();
            DB::table('cfg_clinic_settings')->updateOrInsert(
                [],
                [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'ruc' => '20999999999',
                    'razon_social' => 'VetSaaS Demo SAC',
                    'nombre_comercial' => 'Clínica Veterinaria Demo',
                    'direccion_fiscal' => 'Av. Demo 123, Lima',
                    'color_primario' => '#1F6F43',
                    'color_secundario' => '#94C7A8',
                    'email_institucional' => 'contacto@demo.test',
                    'telefono_principal' => '+51 1 555-0100',
                    'web_url' => 'https://demo.test',
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
