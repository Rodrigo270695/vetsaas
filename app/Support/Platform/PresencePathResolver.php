<?php

declare(strict_types=1);

namespace App\Support\Platform;

/**
 * Deriva un módulo de producto a partir de la URL actual del usuario.
 */
final class PresencePathResolver
{
    /**
     * @return array{path: string, module: string}
     */
    public static function resolve(?string $rawPath): array
    {
        $path = self::normalizePath($rawPath);

        return [
            'path' => $path,
            'module' => self::moduleFromPath($path),
        ];
    }

    public static function normalizePath(?string $rawPath): string
    {
        $path = trim((string) $rawPath);

        if ($path === '') {
            return '/';
        }

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $path = preg_replace('#\?.*$#', '', $path) ?? $path;
        $path = preg_replace('#\#.*$#', '', $path) ?? $path;
        $path = preg_replace('#/{2,}#', '/', $path) ?? $path;

        if (strlen($path) > 512) {
            $path = substr($path, 0, 512);
        }

        return $path === '' ? '/' : $path;
    }

    public static function moduleFromPath(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if ($segments === []) {
            return 'dashboard';
        }

        $first = strtolower($segments[0]);
        $second = strtolower($segments[1] ?? '');

        return match ($first) {
            'dashboard' => 'dashboard',
            'plataforma' => 'plataforma',
            'settings', 'settings/profile', 'profile' => 'settings',
            'ayuda' => 'ayuda',
            'onboarding' => 'onboarding',
            'comunicaciones' => match ($second) {
                'bot-ia' => 'bot_ia',
                default => 'comunicaciones',
            },
            'clinica' => match ($second) {
                'pacientes' => 'pacientes',
                'propietarios' => 'propietarios',
                'citas' => 'citas',
                'historias-clinicas' => 'historias_clinicas',
                'vacunaciones' => 'vacunaciones',
                'recetas' => 'recetas',
                'laboratorio' => 'laboratorio',
                'cirugias' => 'cirugias',
                'hospitalizacion' => 'hospitalizacion',
                default => 'clinica',
            },
            'servicios' => match ($second) {
                'grooming' => 'grooming',
                'hotel' => 'hotel',
                default => 'servicios',
            },
            'inventario' => match ($second) {
                'productos' => 'productos',
                'categorias' => 'categorias',
                'stock' => 'stock',
                'movimientos' => 'movimientos',
                'compras' => 'compras',
                'proveedores' => 'proveedores',
                'alertas' => 'alertas_stock',
                default => 'inventario',
            },
            'caja' => match ($second) {
                'sesiones' => 'caja_sesiones',
                'ventas' => 'ventas',
                'pagos' => 'pagos',
                'descuentos' => 'descuentos',
                default => 'caja',
            },
            'facturacion' => 'facturacion',
            'reportes' => 'reportes',
            'configuracion' => match ($second) {
                'sedes' => 'sedes',
                'usuarios' => 'usuarios',
                'roles' => 'roles',
                'horarios' => 'horarios',
                'tarifas' => 'tarifas',
                'general' => 'config_general',
                'suscripcion' => 'suscripcion',
                default => 'configuracion',
            },
            'auditoria' => 'auditoria',
            default => $first,
        };
    }
}
