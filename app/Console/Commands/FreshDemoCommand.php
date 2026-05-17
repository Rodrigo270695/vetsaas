<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Resetea la base de datos a un estado de demo listo para usar.
 *
 * Hace, en orden:
 *
 *   1. **Confirma** la operación (es destructiva). `--force` la salta.
 *   2. **Droppea todos los schemas tenant** (`vet_*`) que hayan
 *      quedado de runs anteriores. `migrate:fresh` solo afecta el
 *      schema `public`, así que sin este paso los schemas tenant
 *      viejos quedan huérfanos en la BD.
 *   3. **`migrate:fresh --seed`** sobre el schema `public`. Aplica
 *      todas las migraciones desde cero y corre el `DatabaseSeeder`
 *      (planes, permisos, superadmin, roles tenant, sedes).
 *   4. **`db:seed --class=DemoTenantsSeeder`** crea 3 clínicas demo
 *      con sus schemas, admins y configuración inicial.
 *   5. Imprime un **resumen con credenciales** de login.
 *
 * Pensado solo para entornos `local` / `development`. En `production`
 * el comando aborta salvo que se pase `--allow-production` (no usar
 * salvo emergencia: borra TODO).
 *
 * Uso típico:
 *
 *     # Reset completo + demo (con prompt de confirmación)
 *     php artisan vetsaas:fresh-demo
 *
 *     # Sin prompt (útil en scripts / hooks de git)
 *     php artisan vetsaas:fresh-demo --force
 */
class FreshDemoCommand extends Command
{
    protected $signature = 'vetsaas:fresh-demo
                            {--force : No pedir confirmación}
                            {--allow-production : Permitir ejecutar en producción (peligroso)}';

    protected $description = 'Resetea la BD: migrate:fresh + seed + 3 tenants demo con admins y configuración inicial.';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Solo soportado en PostgreSQL (multi-schema). Driver actual: '.DB::getDriverName());

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Comando bloqueado en producción. Usa --allow-production si estás MUY seguro.');

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

        $this->dropTenantSchemas();
        $this->freshAndSeedPublic();
        $this->seedDemoTenants();
        $this->printSummary();

        return self::SUCCESS;
    }

    /**
     * Borra todos los schemas que empiezan con el prefijo de tenant
     * (por defecto `vet_*`). `migrate:fresh` solo conoce el schema
     * `public`; sin este paso los schemas viejos se quedan con tablas
     * fantasma sin foreign keys válidas a `public.tenants`.
     */
    private function dropTenantSchemas(): void
    {
        $prefix = (string) config('tenant.schema_prefix', 'vet_');

        $rows = DB::select(
            "SELECT nspname FROM pg_namespace WHERE nspname LIKE ?",
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

    /**
     * `migrate:fresh --seed`. Aplica todas las migraciones de `public`
     * desde cero y corre el `DatabaseSeeder` (que no incluye
     * `DemoTenantsSeeder` para mantener limpia la separación entre
     * infraestructura y data de demo).
     */
    private function freshAndSeedPublic(): void
    {
        $this->line('• migrate:fresh --seed (schema public)...');
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ], $this->output);
    }

    /**
     * Dispara el seeder de demo. Lo separamos en un paso explícito
     * para que pueda volver a correrse aisladamente sin necesidad
     * de tocar `public` (`php artisan db:seed --class=DemoTenantsSeeder`).
     */
    private function seedDemoTenants(): void
    {
        $this->newLine();
        $this->line('• Sembrando 3 tenants demo (schemas, admins, configuración inicial)...');
        $this->newLine();

        Artisan::call('db:seed', [
            '--class' => 'DemoTenantsSeeder',
            '--force' => true,
        ], $this->output);
    }

    /**
     * Imprime un cheat-sheet con los hosts y credenciales para que el
     * desarrollador pueda copy-pastear en el navegador sin pensar.
     */
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
        $this->line('    URL:      http://localhost:8000/login');
        $this->line('    Email:    '.$superEmail);
        $this->line('    Password: '.$superPass);
        $this->line('');
        $this->line('  Admins de clínica (password: clave123456):');
        $this->line('    · http://mi-clinica.localhost:8000/login   → admin@mi-clinica.test');
        $this->line('    · http://vet-amigos.localhost:8000/login   → admin@vet-amigos.test');
        $this->line('    · http://paws-care.localhost:8000/login    → admin@paws-care.test');
        $this->line('');
        $this->line('  Sugerencia: para que los subdominios *.localhost resuelvan en Windows,');
        $this->line('  ya son resueltos automáticamente por Chrome/Edge (no requiere hosts).');
        $this->newLine();
    }
}
