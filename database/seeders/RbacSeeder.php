<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Bootstrap mínimo de autenticación y autorización (sin datos de negocio).
 *
 * Ejecuta, en orden:
 *   1. PermissionsSeeder — catálogo de permisos en `permissions`.
 *   2. SuperadminSeeder — rol `superadmin`, usuario plataforma (`tenant_id` null).
 *   3. TenantRolesSeeder — roles base de clínica (admin_clinica, veterinario, …).
 *
 * No crea planes, tenants, sedes ni contenido demo.
 *
 * Requiere en `.env`:
 *   PLATFORM_SUPERADMIN_EMAIL
 *   PLATFORM_SUPERADMIN_PASSWORD
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionsSeeder::class,
            SuperadminSeeder::class,
            TenantRolesSeeder::class,
        ]);
    }
}
