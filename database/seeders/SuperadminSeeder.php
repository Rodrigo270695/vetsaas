<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Crea (o re-sincroniza) el rol `superadmin` y el usuario superadmin.
 *
 * El rol `superadmin` se queda con **todos los permisos existentes** en BD
 * (cataloga `PermissionsSeeder` primero). Esto incluye permisos de plataforma SaaS
 * y permisos de tenant; al ser quien construye el producto puedes ver/hacer todo.
 *
 * IMPORTANTE: depende de que `PermissionsSeeder` se haya corrido antes.
 */
class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('platform.superadmin.email');
        $password = config('platform.superadmin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            $this->command?->warn('SuperadminSeeder omitido: define PLATFORM_SUPERADMIN_EMAIL y PLATFORM_SUPERADMIN_PASSWORD en .env');

            return;
        }

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            $role = Role::query()
                ->whereNull('tenant_id')
                ->where('name', 'superadmin')
                ->where('guard_name', 'web')
                ->first();

            if ($role === null) {
                $role = Role::query()->create([
                    'name' => 'superadmin',
                    'guard_name' => 'web',
                    'tenant_id' => null,
                ]);
            }

            $allPermissions = Permission::query()
                ->where('guard_name', 'web')
                ->pluck('id')
                ->all();

            $role->syncPermissions($allPermissions);

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => config('platform.superadmin.name', 'Super Administrador'),
                    'password' => $password,
                    'email_verified_at' => now(),
                    'tenant_id' => null,
                ],
            );

            $user->syncRoles([$role]);
        } finally {
            setPermissionsTeamId($previousTeam);
        }

        $this->command?->info(sprintf(
            'Superadmin creado: %s (rol: superadmin, %d permisos)',
            $email,
            Permission::query()->where('guard_name', 'web')->count(),
        ));
    }
}
