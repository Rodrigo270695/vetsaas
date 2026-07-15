<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Resetea la base de datos a un estado de demo listo para usar.
 *
 * ⚠️  SOLO desarrollo / staging. En producción este comando NO borra
 * clínicas: usa `php artisan vetsaas:reset-demo --rebuild`.
 *
 * Orden (local):
 *   1. Confirmar
 *   2. Verificar que migrate:fresh esté permitido
 *   3. DROP schemas vet_*
 *   4. migrate:fresh --seed
 *   5. DemoTenantsSeeder
 */
class FreshDemoCommand extends Command
{
    protected $signature = 'vetsaas:fresh-demo
                            {--force : No pedir confirmación}';

    protected $description = 'Resetea la BD: migrate:fresh + seed + tenant(s) demo. Solo local/staging.';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Solo soportado en PostgreSQL (multi-schema). Driver actual: '.DB::getDriverName());

            return self::FAILURE;
        }

        if (app()->isProduction()) {
            $this->error('Bloqueado en producción.');
            $this->line('Para resetear SOLO la demo sin tocar otras clínicas:');
            $this->line('  php artisan vetsaas:reset-demo --rebuild');
            $this->newLine();
            $this->warn('Si ya corriste un fresh-demo que dropeó schemas, restaura con:');
            $this->line('  php artisan vetsaas:tenant-restore {slug} {carpeta-backup} --force');

            return self::FAILURE;
        }

        $this->warn('==============================================================');
        $this->warn('  Esto BORRARÁ toda la base de datos y los schemas tenant.');
        $this->warn('  Solo úsalo en desarrollo.');
        $this->warn('==============================================================');

        if (! $this->option('force') && ! $this->confirm('¿Continuar?', false)) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        // Verificar ANTES de dropear schemas: en production Laravel
        // prohíbe migrate:fresh y dejaría las clínicas sin schema.
        if (app()->isProduction()) {
            $this->error('migrate:fresh está prohibido en este entorno. Abortando sin cambios.');

            return self::FAILURE;
        }

        $this->dropTenantSchemas();

        $exit = $this->freshAndSeedPublic();
        if ($exit !== self::SUCCESS) {
            $this->error('migrate:fresh falló. Revisá el entorno (no uses esto en production).');

            return self::FAILURE;
        }

        $this->seedDemoTenants();
        $this->printSummary();

        return self::SUCCESS;
    }

    private function dropTenantSchemas(): void
    {
        $prefix = (string) config('tenant.schema_prefix', 'vet_');

        $rows = DB::select(
            'SELECT nspname FROM pg_namespace WHERE nspname LIKE ?',
            [$prefix.'%'],
        );

        if ($rows === []) {
            $this->line('• No hay schemas tenant previos que limpiar.');

            return;
        }

        $this->line('• Eliminando '.count($rows).' schema(s) tenant previos...');

        foreach ($rows as $row) {
            $schema = $row->nspname;
            DB::statement('DROP SCHEMA IF EXISTS "'.$schema.'" CASCADE');
            $this->line('  - '.$schema);
        }
    }

    private function freshAndSeedPublic(): int
    {
        $this->line('• migrate:fresh --seed (schema public)...');

        return Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ], $this->output);
    }

    private function seedDemoTenants(): void
    {
        $this->newLine();
        $this->line('• Sembrando tenant(s) demo…');
        $this->newLine();

        Artisan::call('db:seed', [
            '--class' => 'DemoTenantsSeeder',
            '--force' => true,
        ], $this->output);
    }

    private function printSummary(): void
    {
        $superEmail = config('platform.superadmin.email', 'superadmin@vetsaas.com');
        $superPass = config('platform.superadmin.password', '(definido en .env)');

        $this->newLine();
        $this->info('==============================================================');
        $this->info('  Listo. Credenciales de demo:');
        $this->info('==============================================================');
        $this->line('');
        $this->line('  Superadmin (host central):');
        $this->line('    Email:    '.$superEmail);
        $this->line('    Password: '.$superPass);
        $this->line('');
        $this->line('  Demo clínica:');
        $this->line('    URL:      http://demo.localhost:8000/login');
        $this->line('    Email:    demo@vetsaas.pe');
        $this->line('    Password: demo1234');
        $this->newLine();
    }
}
