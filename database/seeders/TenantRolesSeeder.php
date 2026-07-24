<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea (o re-sincroniza) los **roles base de cada clínica/tenant**.
 *
 * Coinciden con los roles definidos en `vetsaas_db.md` §5.1:
 *
 *   - admin_clinica  → dueño de la clínica (todo acceso menos plataforma SaaS).
 *   - veterinario    → acceso clínico completo (atender, recetar, operar).
 *   - asistente_vet  → apoyo clínico: lectura + crear citas/vacunaciones.
 *   - recepcionista  → agenda, caja y atención al cliente / facturación.
 *   - groomer        → solo módulo de peluquería y datos básicos del paciente.
 *
 * Características:
 *
 *   - **Por tenant**: cada clínica tiene sus propias filas de roles
 *     (`roles.tenant_id`). Cambiar permisos en A no afecta a B.
 *   - **Idempotente**: `firstOrCreate` por (tenant_id, name) + `syncPermissions()`.
 *   - Roles protegidos vía `Role::BASE_CLINIC_ROLES` (no eliminables/renombrables).
 *   - Depende de `PermissionsSeeder` (los permisos deben existir antes).
 *
 * Uso:
 *   - `php artisan db:seed --class=TenantRolesSeeder` → siembra todos los tenants.
 *   - `(new TenantRolesSeeder)->seedForTenant($tenantId)` → un tenant concreto.
 */
class TenantRolesSeeder extends Seeder
{
    /**
     * Definición de roles tenant.
     *
     * Cada entrada incluye:
     *   - `description`: texto humano para mostrar en el listado.
     *   - `permissions`: lista de permisos por defecto que se sincronizarán.
     *
     * Si un permiso listado todavía no existe en BD (ej. olvidaste correr
     * `PermissionsSeeder` antes), simplemente se ignora — se asignan solo
     * los que existen para no romper el seeder.
     *
     * @var array<string, array{description: string, permissions: array<int, string>}>
     */
    public const ROLES = [
        /* ────────────────────────────────────────────────────────────────
         | admin_clinica
         | Dueño de la clínica. Tiene TODO el acceso operativo del tenant,
         | incluyendo configuración, usuarios, sedes, roles y reportes.
         | Lo único excluido son los permisos de plataforma SaaS
         | (`plataforma-*`) y el flag transversal `audit-trail` que es
         | de uso interno del superadmin.
         ──────────────────────────────────────────────────────────────── */
        'admin_clinica' => [
            'description' => 'Dueño o administrador de la clínica. Acceso operativo total dentro del tenant: configuración, usuarios, finanzas y módulos clínicos.',
            'permissions' => [
                // Dashboard
                'dashboard.view',
                'in-app-assistant.use',

                // Clínica
                'pacientes.view', 'pacientes.create', 'pacientes.update', 'pacientes.delete', 'pacientes.export', 'pacientes.bulk-delete',
                'petpass.view', 'petpass.register',
                'propietarios.view', 'propietarios.create', 'propietarios.update', 'propietarios.delete', 'propietarios.export', 'propietarios.bulk-delete',
                'citas.view', 'citas.create', 'citas.update', 'citas.delete', 'citas.cancel', 'citas.aperturar',
                'historias-clinicas.view', 'historias-clinicas.create', 'historias-clinicas.update', 'historias-clinicas.delete',
                'historias-clinicas-planes.view', 'historias-clinicas-planes.manage',
                'vacunaciones.view', 'vacunaciones.create', 'vacunaciones.update', 'vacunaciones.delete',
                'recetas.view', 'recetas.create', 'recetas.update', 'recetas.delete',
                'laboratorio.view', 'laboratorio.create', 'laboratorio.update', 'laboratorio.delete',
                'cirugias.view', 'cirugias.create', 'cirugias.update', 'cirugias.delete',
                'consulta-cargos.view', 'consulta-cargos.manage', 'consulta-cargos.cobrar',
                'hospitalizacion.view', 'hospitalizacion.create', 'hospitalizacion.update', 'hospitalizacion.delete',

                // Servicios
                'grooming.view', 'grooming.create', 'grooming.update', 'grooming.delete',
                'hotel.view', 'hotel.create', 'hotel.update', 'hotel.delete',

                // Inventario
                'productos.view', 'productos.create', 'productos.update', 'productos.delete',
                'categorias-inventario.view', 'categorias-inventario.create', 'categorias-inventario.update', 'categorias-inventario.delete',
                'stock.view', 'stock.adjust',
                'movimientos-stock.view', 'movimientos-stock.create', 'movimientos-stock.export',
                'alertas-stock.view',
                'proveedores.view', 'proveedores.create', 'proveedores.update', 'proveedores.delete',
                'compras.view', 'compras.create', 'compras.update', 'compras.delete',

                // Caja & ventas
                'caja-sesiones.view', 'caja-sesiones.open', 'caja-sesiones.close', 'caja-sesiones.egreso',
                'ventas.view', 'ventas.create', 'ventas.update', 'ventas.delete',
                'pagos.view', 'pagos.create', 'pagos.refund',
                'descuentos.view', 'descuentos.create', 'descuentos.update', 'descuentos.delete',

                // Facturación
                'documentos.view', 'documentos.create', 'documentos.send', 'documentos.cancel',
                'series.view', 'series.create', 'series.update', 'series.delete',
                'notas-baja.view', 'notas-baja.create',
                'resumenes.view', 'resumenes.send',

                // Comunicaciones
                'comunicaciones-cola.view', 'comunicaciones-cola.manage',
                'comunicaciones-historico.view',
                'comunicaciones-bot-ia.view', 'comunicaciones-bot-ia.manage',
                'plantillas.view', 'plantillas.create', 'plantillas.update', 'plantillas.delete',

                // Reportes
                'snapshots.view', 'snapshots.export',
                'reporte-financiero.view', 'reporte-financiero.export',
                'reporte-top-pacientes.view',

                // Configuración del tenant
                'config-general.view', 'config-general.update',
                'sedes.view', 'sedes.create', 'sedes.update', 'sedes.delete', 'sedes.export', 'sedes.bulk-delete',
                'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.export', 'roles.bulk-delete',
                'horarios.view', 'horarios.create', 'horarios.update', 'horarios.delete',
                'bloqueos.view', 'bloqueos.create', 'bloqueos.update', 'bloqueos.delete',
                'tarifas.view', 'tarifas.create', 'tarifas.update', 'tarifas.delete',
                'usuarios.view', 'usuarios.create', 'usuarios.update', 'usuarios.delete', 'usuarios.reset-password', 'usuarios.export', 'usuarios.bulk-delete',

                // Auditoría del tenant
                'auditoria-logs.view', 'auditoria-logs.export',
                'auditoria-login-attempts.view',
                'auditoria-api-logs.view',
                'auditoria-tokens.view', 'auditoria-tokens.revoke',
            ],
        ],

        /* ────────────────────────────────────────────────────────────────
         | veterinario
         | Profesional médico. Atiende citas, registra historias clínicas,
         | receta, ordena laboratorio y cirugía. Necesita lectura de
         | inventario/stock para saber qué medicamentos hay disponibles.
         ──────────────────────────────────────────────────────────────── */
        'veterinario' => [
            'description' => 'Médico veterinario. Atiende pacientes, registra historias clínicas, vacunaciones, recetas, laboratorio, cirugías y hospitalización.',
            'permissions' => [
                'dashboard.view',
                'in-app-assistant.use',

                // Pacientes & propietarios (puede crear si recibe walk-in)
                'pacientes.view', 'pacientes.create', 'pacientes.update',
                'petpass.view', 'petpass.register',
                'propietarios.view', 'propietarios.create', 'propietarios.update',

                // Agenda: atiende (apertura); no crea citas (eso es recepción)
                'citas.view', 'citas.aperturar', 'citas.cancel',

                // Núcleo clínico
                'historias-clinicas.view', 'historias-clinicas.create', 'historias-clinicas.update',
                'historias-clinicas-planes.view', 'historias-clinicas-planes.manage',
                'vacunaciones.view', 'vacunaciones.create', 'vacunaciones.update',
                'recetas.view', 'recetas.create', 'recetas.update',
                'laboratorio.view', 'laboratorio.create', 'laboratorio.update',
                'cirugias.view', 'cirugias.create', 'cirugias.update',
                'consulta-cargos.view', 'consulta-cargos.manage',
                'hospitalizacion.view', 'hospitalizacion.create', 'hospitalizacion.update',

                // Inventario (solo lectura: necesita ver qué medicamentos hay)
                'productos.view',
                'categorias-inventario.view',
                'stock.view',
                'alertas-stock.view',

                // Reportes propios
                'reporte-top-pacientes.view',
            ],
        ],

        /* ────────────────────────────────────────────────────────────────
         | asistente_vet
         | Soporte clínico al veterinario. Lectura general del módulo
         | clínico, agenda citas y registra vacunaciones de rutina.
         | NO modifica historias clínicas ni recetas (responsabilidad
         | médica).
         ──────────────────────────────────────────────────────────────── */
        'asistente_vet' => [
            'description' => 'Asistente clínico. Apoya al veterinario: lectura del expediente, agenda citas y registra vacunaciones de rutina.',
            'permissions' => [
                'dashboard.view',
                'in-app-assistant.use',

                'pacientes.view',
                'propietarios.view',

                // Agenda activa
                'citas.view', 'citas.create', 'citas.update', 'citas.cancel',

                // Lectura clínica
                'historias-clinicas.view',
                'historias-clinicas-planes.view',
                'vacunaciones.view', 'vacunaciones.create',
                'recetas.view',
                'laboratorio.view',
                'cirugias.view',
                'consulta-cargos.view',
                'hospitalizacion.view',

                // Inventario lectura
                'productos.view',
                'categorias-inventario.view',
                'stock.view',
            ],
        ],

        /* ────────────────────────────────────────────────────────────────
         | recepcionista
         | Front-desk. Agenda, cobra, atiende clientes nuevos y emite
         | comprobantes. No tiene acceso al expediente clínico.
         ──────────────────────────────────────────────────────────────── */
        'recepcionista' => [
            'description' => 'Recepción y front-desk. Agenda citas, atiende clientes, cobra y emite comprobantes. Ve HC solo para cargos/pre-cuenta; no aperturar ni plan clínico.',
            'permissions' => [
                'dashboard.view',
                'in-app-assistant.use',

                // Clientes & pacientes (los registra al ingresar)
                'pacientes.view', 'pacientes.create', 'pacientes.update',
                'petpass.view', 'petpass.register',
                'propietarios.view', 'propietarios.create', 'propietarios.update',

                // Agenda completa (sin aperturar: eso lo hace el veterinario)
                'citas.view', 'citas.create', 'citas.update', 'citas.cancel',

                // Lista HC solo para cobro (cargos); sin plan clínico ni crear/editar consultas
                'historias-clinicas.view',

                // Pre-cuenta por consulta (cobro en recepción)
                'consulta-cargos.view', 'consulta-cargos.manage', 'consulta-cargos.cobrar',

                // Servicios hotel/guardería (front-desk registra y cobra con ventas)
                'hotel.view', 'hotel.create', 'hotel.update',
                'grooming.view',

                // Caja & ventas
                'caja-sesiones.view', 'caja-sesiones.open', 'caja-sesiones.close', 'caja-sesiones.egreso',
                'ventas.view', 'ventas.create', 'ventas.delete',
                'pagos.view', 'pagos.create',
                'descuentos.view',
                'categorias-inventario.view',
                'proveedores.view', 'proveedores.create', 'proveedores.update',

                // Facturación
                'documentos.view', 'documentos.create', 'documentos.send',
                'series.view',

                // Comunicaciones (puede revisar el estado de los WhatsApp)
                'comunicaciones-cola.view',
                'comunicaciones-historico.view',
            ],
        ],

        /* ────────────────────────────────────────────────────────────────
         | groomer
         | Peluquero canino. Solo opera el módulo grooming + lectura
         | mínima de paciente/propietario para contactar al dueño.
         ──────────────────────────────────────────────────────────────── */
        'groomer' => [
            'description' => 'Peluquero canino / felino. Solo opera el módulo de grooming y consulta datos básicos del paciente y propietario.',
            'permissions' => [
                'dashboard.view',
                'in-app-assistant.use',

                // Necesita ver al paciente y contactar al dueño
                'pacientes.view',
                'propietarios.view',

                // Citas: solo lectura (las agenda recepción)
                'citas.view',

                // Su módulo principal
                'grooming.view', 'grooming.create', 'grooming.update',

                // A veces el grooming overlaps con hotelería
                'hotel.view',
            ],
        ],
    ];

    public function run(): void
    {
        $tenantIds = Tenant::query()->pluck('id')->all();

        if ($tenantIds === []) {
            $this->command?->warn('TenantRolesSeeder: no hay tenants; nada que sembrar. Los roles se crean al provisionar cada clínica.');

            return;
        }

        foreach ($tenantIds as $tenantId) {
            $this->seedForTenant((string) $tenantId);
        }
    }

    /**
     * Crea/sincroniza los roles base para un tenant concreto.
     */
    public function seedForTenant(string $tenantId): void
    {
        $guard = config('auth.defaults.guard', 'web');

        if (! Schema::hasTable(config('permission.table_names.roles'))) {
            $this->command?->error('No existe la tabla de roles. Ejecuta `php artisan migrate` primero.');

            return;
        }

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId($tenantId);

        try {
            $validPermissionNames = Permission::query()
                ->where('guard_name', $guard)
                ->pluck('name')
                ->all();
            $validPermissionSet = array_flip($validPermissionNames);

            if ($validPermissionSet === []) {
                $this->command?->warn('No hay permisos en BD. Corre primero: php artisan db:seed --class=PermissionsSeeder');

                return;
            }

            foreach (self::ROLES as $name => $definition) {
                $role = Role::query()->firstOrCreate(
                    [
                        'name' => $name,
                        'guard_name' => $guard,
                        'tenant_id' => $tenantId,
                    ],
                    ['description' => $definition['description']],
                );

                if ($role->description !== $definition['description']) {
                    $role->description = $definition['description'];
                    $role->save();
                }

                $perms = array_values(array_filter(
                    $definition['permissions'],
                    fn (string $perm) => isset($validPermissionSet[$perm]),
                ));

                $missing = array_diff($definition['permissions'], $perms);
                if (! empty($missing)) {
                    $this->command?->warn(sprintf(
                        "Rol `%s` (tenant %s): %d permisos no existen en BD y serán ignorados:\n  - %s",
                        $name,
                        $tenantId,
                        count($missing),
                        implode("\n  - ", $missing),
                    ));
                }

                $role->syncPermissions($perms);

                $this->command?->info(sprintf(
                    'Rol `%s` listo para tenant %s (%d permisos).',
                    $name,
                    $tenantId,
                    count($perms),
                ));
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }
}
