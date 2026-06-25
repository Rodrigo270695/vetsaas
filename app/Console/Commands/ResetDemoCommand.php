<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Resetea los datos clínicos del tenant demo y restaura la contraseña.
 *
 * Se ejecuta automáticamente cada noche a las 3:00 a.m. (ver bootstrap/app.php).
 * También se puede correr a mano en cualquier momento:
 *
 *   php artisan vetsaas:reset-demo
 *
 * Qué hace:
 *   1. Verifica que el tenant "demo" exista (si no, avisa y sale).
 *   2. Restaura la contraseña a "demo1234" — por si alguien la cambió.
 *   3. Corre DemoDataSeeder: trunca tablas operativas y vuelve a sembrar
 *      pacientes, historial, citas, caja y stock con fechas frescas.
 *
 * Qué NO hace:
 *   - No toca la estructura del schema (tablas, columnas, índices).
 *   - No recrea el tenant ni el usuario admin.
 *   - No afecta a ningún otro tenant.
 */
final class ResetDemoCommand extends Command
{
    protected $signature   = 'vetsaas:reset-demo';
    protected $description = 'Resetea datos y contraseña del tenant demo (corre automáticamente cada noche)';

    private const DEMO_SLUG     = 'demo';
    private const DEMO_EMAIL    = 'demo@vetsaas.pe';
    private const DEMO_PASSWORD = 'demo1234';

    public function handle(): int
    {
        $this->info('── Reset demo ─────────────────────────────────');

        // 1. Verificar que el tenant existe.
        $tenant = Tenant::query()->where('slug', self::DEMO_SLUG)->first();

        if ($tenant === null) {
            $this->error('Tenant "demo" no encontrado. Ejecuta primero: php artisan db:seed --class=DemoTenantsSeeder');

            return self::FAILURE;
        }

        // 2. Restaurar contraseña (por si algún travieso la cambió).
        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', self::DEMO_EMAIL)
            ->first();

        if ($user !== null) {
            $user->password              = Hash::make(self::DEMO_PASSWORD);
            $user->must_change_password  = false;
            $user->save();
            $this->line('  → Contraseña restaurada a "demo1234".');
        } else {
            $this->warn('  ⚠ Usuario demo@vetsaas.pe no encontrado — omitiendo reset de contraseña.');
        }

        // 3. Refrescar datos clínicos.
        $this->line('  → Recargando datos clínicos (pacientes, citas, caja, stock)…');

        $seeder = new DemoDataSeeder;
        $seeder->setCommand($this);
        $seeder->run();

        $this->info('✓ Demo listo — demo@vetsaas.pe / demo1234');

        return self::SUCCESS;
    }
}
