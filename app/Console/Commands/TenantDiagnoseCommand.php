<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Tenancy\TenantSubdomainUrl;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Diagnóstico rápido de un tenant (schema, estado, URL de login).
 */
class TenantDiagnoseCommand extends Command
{
    protected $signature = 'vetsaas:tenant-diagnose {slug : Slug del subdominio}';

    protected $description = 'Comprueba registro, schema PostgreSQL y URL de login de un tenant';

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
            $manager->runForSlug($slug, function () use ($schema): void {
                $hasClinic = Schema::hasTable('cfg_clinic_settings');
                $this->line('  cfg_clinic_settings: '.($hasClinic ? 'sí' : 'no'));

                if (! $hasClinic) {
                    $this->warn("Migraciones pendientes en {$schema}. Ejecuta: php artisan vetsaas:tenant-migrate {$schema}");
                }
            });
        } catch (Throwable $e) {
            $this->error('Error al montar el tenant: '.$e->getMessage());
            $this->warn('Si el error menciona __PHP_Incomplete_Class, ejecuta: php artisan cache:clear');

            return self::FAILURE;
        }

        $this->info('Diagnóstico completado.');

        return self::SUCCESS;
    }
}
