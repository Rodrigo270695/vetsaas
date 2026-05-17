<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

        $role = Role::query()->firstOrCreate(
            ['name' => 'superadmin', 'guard_name' => 'web'],
        );

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
            ],
        );

        $user->syncRoles([$role]);

        $this->command?->info(sprintf(
            'Superadmin creado: %s (rol: superadmin, %d permisos)',
            $email,
            count($allPermissions),
        ));
    }
}
