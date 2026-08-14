<?php

namespace App\Console\Commands;

use App\Models\Departamento;
use App\Models\Sede;
use App\Models\Tenant;
use App\Support\Tenancy\TenantSubdomainUrl;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Diagnóstico rápido de un tenant (schema, estado, URL de login, sedes).
 */
class TenantDiagnoseCommand extends Command
{
    protected $signature = 'vetsaas:tenant-diagnose {slug : Slug del subdominio}';

    protected $description = 'Comprueba registro, schema PostgreSQL, sedes/geo y URL de login de un tenant';

    public function handle(TenantManager $manager): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Este comando requiere PostgreSQL.');

            return self::FAILURE;
        }

        $slug = strtolower(trim((string) $this->argument('slug')));
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->error("No existe tenant con slug: {$slug}");

            return self::FAILURE;
        }

        $this->info("Tenant: {$tenant->razon_social} ({$tenant->id})");
        $this->line("  estado: {$tenant->estado}");
        $this->line('  schema_name: '.$tenant->schema_name);
        $this->line('  login_url: '.TenantSubdomainUrl::login($tenant));

        $schema = (string) $tenant->schema_name;
        $exists = (bool) DB::selectOne(
            'SELECT EXISTS(SELECT 1 FROM information_schema.schemata WHERE schema_name = ?) AS ok',
            [$schema]
        )?->ok;

        $this->line('  schema_exists: '.($exists ? 'sí' : 'no'));

        if (! $exists) {
            $this->warn('El schema no existe. Ejecuta: php artisan vetsaas:tenant-migrate '.$schema);

            return self::FAILURE;
        }

        $manager->flushCacheFor($tenant);

        try {
            $manager->runForSlug($slug, function () use ($schema, $tenant): void {
                $hasClinic = Schema::hasTable('cfg_clinic_settings');
                $this->line('  cfg_clinic_settings: '.($hasClinic ? 'sí' : 'no'));

                if (! $hasClinic) {
                    $this->warn("Migraciones pendientes en {$schema}. Ejecuta: php artisan vetsaas:tenant-migrate {$schema}");
                }

                $sedes = Sede::query()
                    ->where('tenant_id', $tenant->id)
                    ->get(['id', 'codigo', 'nombre', 'activa', 'distrito_id', 'distrito', 'provincia', 'departamento']);

                $this->line('  sedes: '.$sedes->count());
                foreach ($sedes as $sede) {
                    $this->line(sprintf(
                        '    · %s | %s | activa=%s | distrito_id=%s | %s / %s / %s',
                        $sede->codigo,
                        $sede->nombre,
                        $sede->activa ? 'sí' : 'no',
                        $sede->distrito_id === null ? 'null' : (string) $sede->distrito_id,
                        $sede->departamento ?: '—',
                        $sede->provincia ?: '—',
                        $sede->distrito ?: '—',
                    ));
                }

                $this->line('  Probando query de /configuracion/sedes …');
                $page = Sede::query()
                    ->where('tenant_id', $tenant->id)
                    ->with(['distritoModel.provincia.departamento', 'creadoPor:id,name,email'])
                    ->orderByDesc('created_at')
                    ->paginate(10);
                $departamentos = Departamento::query()->where('status', true)->orderBy('name')->get(['id', 'name']);
                $jsonBytes = strlen(json_encode([
                    'sedes' => $page->toArray(),
                    'departamentos' => $departamentos->toArray(),
                ], JSON_THROW_ON_ERROR));
                $this->info("  Query sedes OK (json ~{$jsonBytes} bytes)");
            });
        } catch (Throwable $e) {
            $this->error('Error al montar el tenant / sedes: '.$e->getMessage());
            $this->line('  '.$e::class.' @ '.$e->getFile().':'.$e->getLine());
            $this->warn('Si el error menciona __PHP_Incomplete_Class, ejecuta: php artisan cache:clear');

            return self::FAILURE;
        }

        $this->info('Diagnóstico completado.');

        return self::SUCCESS;
    }
}
