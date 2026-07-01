<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Catálogo de módulos operativos por tenant (sidebar, dashboard, tarifas).
 *
 * La clave es el identificador estable guardado en `tenants.modulos_deshabilitados`.
 */
final class TenantModuleRegistry
{
    /** @var list<string> */
    public const ALL_KEYS = [
        'pacientes',
        'propietarios',
        'citas',
        'historias_clinicas',
        'vacunaciones',
        'recetas',
        'laboratorio',
        'cirugias',
        'hospitalizacion',
        'grooming',
        'hotel',
        'productos',
        'categorias_inventario',
        'stock',
        'movimientos_stock',
        'alertas_stock',
        'proveedores',
        'compras',
        'caja_sesiones',
        'ventas',
        'pagos',
        'descuentos',
        'documentos',
        'series',
        'notas_baja',
        'resumenes',
        'comunicaciones_cola',
        'comunicaciones_historico',
        'bot_ia',
        'plantillas',
        'snapshots',
        'reporte_financiero',
        'reporte_top_pacientes',
        'config_general',
        'config_suscripcion',
        'sedes',
        'roles',
        'horarios',
        'bloqueos',
        'tarifas',
        'usuarios',
        'auditoria_logs',
        'auditoria_login_attempts',
        'auditoria_api_logs',
        'auditoria_tokens',
    ];

    /**
     * Mapeo módulo → capability del dashboard (si aplica).
     *
     * @var array<string, string>
     */
    public const CAPABILITY_MAP = [
        'citas' => 'citas',
        'historias_clinicas' => 'consultas',
        'vacunaciones' => 'vacunaciones',
        'grooming' => 'grooming',
        'hotel' => 'hotel',
        'hospitalizacion' => 'hospitalizacion',
        'pacientes' => 'pacientes',
        'propietarios' => 'propietarios',
        'productos' => 'productos',
        'alertas_stock' => 'alertas_stock',
        'ventas' => 'ventas',
        'caja_sesiones' => 'caja_sesiones',
    ];

    /**
     * Tabs de tarifas controlados por módulo de servicio.
     *
     * @var array<string, string>
     */
    public const TARIFAS_TAB_MAP = [
        'grooming' => 'grooming',
        'hotel' => 'hotel',
    ];

    /**
     * Grupos para la UI del superadmin (mismo orden que el sidebar).
     *
     * @return list<array{group: string, modules: list<string>}>
     */
    public static function groups(): array
    {
        return [
            [
                'group' => 'clinica',
                'modules' => [
                    'pacientes',
                    'propietarios',
                    'citas',
                    'historias_clinicas',
                    'vacunaciones',
                    'recetas',
                    'laboratorio',
                    'cirugias',
                    'hospitalizacion',
                ],
            ],
            [
                'group' => 'servicios',
                'modules' => ['grooming', 'hotel'],
            ],
            [
                'group' => 'inventario',
                'modules' => [
                    'productos',
                    'categorias_inventario',
                    'stock',
                    'movimientos_stock',
                    'alertas_stock',
                    'proveedores',
                    'compras',
                ],
            ],
            [
                'group' => 'caja',
                'modules' => ['caja_sesiones', 'ventas', 'pagos', 'descuentos'],
            ],
            [
                'group' => 'facturacion',
                'modules' => ['documentos', 'series', 'notas_baja', 'resumenes'],
            ],
            [
                'group' => 'comunicaciones',
                'modules' => [
                    'comunicaciones_cola',
                    'comunicaciones_historico',
                    'bot_ia',
                    'plantillas',
                ],
            ],
            [
                'group' => 'reportes',
                'modules' => ['snapshots', 'reporte_financiero', 'reporte_top_pacientes'],
            ],
            [
                'group' => 'configuracion',
                'modules' => [
                    'config_general',
                    'config_suscripcion',
                    'sedes',
                    'roles',
                    'horarios',
                    'bloqueos',
                    'tarifas',
                    'usuarios',
                ],
            ],
            [
                'group' => 'auditoria',
                'modules' => [
                    'auditoria_logs',
                    'auditoria_login_attempts',
                    'auditoria_api_logs',
                    'auditoria_tokens',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function validateKeys(array $keys): array
    {
        return array_values(array_unique(array_filter(
            $keys,
            static fn (mixed $key): bool => is_string($key) && in_array($key, self::ALL_KEYS, true),
        )));
    }
}
