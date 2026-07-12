<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cataloga **todos** los permisos de la aplicación.
 *
 * Convención de nombres: `<modulo>.<accion>` en minúsculas y separados por punto.
 *   - `<modulo>`  : recurso de negocio (sedes, pacientes, propietarios, …).
 *   - `<accion>`  : verbo CRUD u operación específica (view, create, update, delete,
 *                   manage, export, import, restore, etc.).
 *
 * Reglas:
 *   - **Idempotente**: se puede correr múltiples veces sin duplicar filas.
 *   - **No asigna** permisos a roles: eso lo hace `SuperadminSeeder` (y, a futuro,
 *     `RolesSeeder` para roles tenant: admin_clinica, veterinario, recepcionista, etc.).
 *   - Si un permiso desaparece del catálogo, queda en BD (no se borra automáticamente
 *     para preservar histórico). Si necesitas limpiar, hazlo manualmente.
 */
class PermissionsSeeder extends Seeder
{
    /**
     * Catálogo maestro de permisos por módulo.
     * Agregar aquí cualquier permiso nuevo y luego correr `php artisan db:seed --class=PermissionsSeeder`.
     */
    public const CATALOG = [
        // ───── Dashboard ─────
        'dashboard' => ['view'],

        // ───── Clínica ─────
        'pacientes' => ['view', 'create', 'update', 'delete', 'export', 'bulk-delete'],
        'propietarios' => ['view', 'create', 'update', 'delete', 'export', 'bulk-delete'],
        'citas' => ['view', 'create', 'update', 'delete', 'cancel'],
        'historias-clinicas' => ['view', 'create', 'update', 'delete'],
        'historias-clinicas-planes' => ['view', 'manage'],
        'vacunaciones' => ['view', 'create', 'update', 'delete'],
        'recetas' => ['view', 'create', 'update', 'delete'],
        'laboratorio' => ['view', 'create', 'update', 'delete'],
        'cirugias' => ['view', 'create', 'update', 'delete'],
        'consulta-cargos' => ['view', 'manage', 'cobrar'],
        'hospitalizacion' => ['view', 'create', 'update', 'delete'],

        // ───── Servicios ─────
        'grooming' => ['view', 'create', 'update', 'delete'],
        'hotel' => ['view', 'create', 'update', 'delete'],

        // ───── Inventario ─────
        'productos' => ['view', 'create', 'update', 'delete'],
        'categorias-inventario' => ['view', 'create', 'update', 'delete'],
        'stock' => ['view', 'adjust'],
        'movimientos-stock' => ['view', 'create', 'export'],
        'alertas-stock' => ['view'],
        'proveedores' => ['view', 'create', 'update', 'delete'],
        'compras' => ['view', 'create', 'update', 'delete'],

        // ───── Caja ─────
        'caja-sesiones' => ['view', 'open', 'close'],
        'ventas' => ['view', 'create', 'update', 'delete'],
        'pagos' => ['view', 'create', 'refund'],
        'descuentos' => ['view', 'create', 'update', 'delete'],

        // ───── Facturación ─────
        'documentos' => ['view', 'create', 'send', 'cancel'],
        'series' => ['view', 'create', 'update', 'delete'],
        'notas-baja' => ['view', 'create'],
        'resumenes' => ['view', 'send'],

        // ───── Comunicaciones ─────
        'comunicaciones-cola' => ['view', 'manage'],
        'comunicaciones-historico' => ['view'],
        'comunicaciones-bot-ia' => ['view', 'manage'],
        'plantillas' => ['view', 'create', 'update', 'delete'],

        // ───── Reportes ─────
        'snapshots' => ['view', 'export'],
        'reporte-financiero' => ['view', 'export'],
        'reporte-top-pacientes' => ['view'],

        // ───── Configuración ─────
        'config-general' => ['view', 'update'],
        'sedes' => ['view', 'create', 'update', 'delete', 'export', 'bulk-delete'],
        'roles' => ['view', 'create', 'update', 'delete', 'export', 'bulk-delete'],
        'horarios' => ['view', 'create', 'update', 'delete'],
        'bloqueos' => ['view', 'create', 'update', 'delete'],
        'tarifas' => ['view', 'create', 'update', 'delete'],
        'usuarios' => ['view', 'create', 'update', 'delete', 'reset-password', 'export', 'bulk-delete'],

        // ───── Auditoría ─────
        'auditoria-logs' => ['view', 'export'],
        'auditoria-login-attempts' => ['view'],
        'auditoria-api-logs' => ['view'],
        'auditoria-tokens' => ['view', 'revoke'],

        /*
         * Permiso transversal: controla si en CUALQUIER listado se ven
         * columnas/metadatos de auditoría inline ("Creada por", "Actualizada por", etc.).
         * Por convención: superadmin sí; cliente final (admin_clinica) NO.
         */
        'audit-trail' => ['view'],

        // ───── Plataforma (solo superadmin SaaS) ─────
        'plataforma-tenants' => ['view', 'create', 'update', 'suspend', 'resume', 'delete', 'export', 'bulk-delete', 'impersonate', 'whatsapp-restart', 'whatsapp-stop'],
        'plataforma-planes' => ['view', 'create', 'update', 'delete', 'export', 'bulk-delete'],
        'plataforma-suscripciones' => ['view', 'create', 'update', 'delete', 'export', 'bulk-delete', 'extend-trial', 'change-plan', 'cancel', 'toggle-bot-ia'],
        'plataforma-cobros' => ['view', 'export', 'refund', 'resend-invoice', 'add-note'],
        /*
         * Radar operativo del SaaS: salud, WhatsApp, failed jobs,
         * suscripciones en grace y credenciales. `manage` = reintentar jobs.
         */
        'plataforma-operaciones' => ['view', 'manage'],

        /*
         * Configuración global del SaaS: credenciales de Twilio (WhatsApp)
         * y Brevo (correo) que comparten todas las clínicas. Se guardan en
         * `public.platform_settings` y solo el `superadmin` puede tocarlas.
         */
        'platform-settings' => ['view', 'update'],

        /*
         * Base de conocimiento del bot de ventas (salesbot_knowledge).
         * Solo superadmin tiene estos permisos: es la única persona
         * que gestiona los planes, módulos y FAQs del bot de ventas.
         */
        'salesbot-knowledge' => ['view', 'create', 'update', 'delete'],

        /*
         * Novedades in-app del Asistente IA para tenants (banner en Comunicaciones).
         */
        'bot-ia-announcements' => ['view', 'create', 'update', 'delete'],
    ];

    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $existing = Permission::query()
            ->where('guard_name', $guard)
            ->pluck('id', 'name')
            ->all();

        $now = now();
        $toInsert = [];

        foreach (self::expand() as $name) {
            if (isset($existing[$name])) {
                continue;
            }

            $toInsert[] = [
                'name' => $name,
                'guard_name' => $guard,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($toInsert)) {
            DB::table(config('permission.table_names.permissions'))->insert($toInsert);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Expande el catálogo a la lista plana de strings (`modulo.accion`).
     *
     * @return array<int, string>
     */
    public static function expand(): array
    {
        $permissions = [];

        foreach (self::CATALOG as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        sort($permissions);

        return $permissions;
    }
}
