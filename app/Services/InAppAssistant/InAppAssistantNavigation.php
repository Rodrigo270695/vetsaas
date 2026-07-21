<?php

declare(strict_types=1);

namespace App\Services\InAppAssistant;

use App\Models\User;

/**
 * Destinos de navegación conocidos para el asistente in-app.
 */
final class InAppAssistantNavigation
{
    /**
     * @return list<array{id: string, label: string, url: string, aliases: list<string>, required_permissions: list<string>}>
     */
    public static function destinations(?User $user = null): array
    {
        $destinations = [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'url' => '/dashboard', 'aliases' => ['inicio', 'resumen'], 'required_permissions' => ['dashboard.view']],
            ['id' => 'pacientes', 'label' => 'Pacientes', 'url' => '/clinica/pacientes', 'aliases' => ['paciente', 'mascotas', 'historial'], 'required_permissions' => ['pacientes.view']],
            ['id' => 'propietarios', 'label' => 'Propietarios', 'url' => '/clinica/propietarios', 'aliases' => ['dueños', 'duenos', 'titulares', 'clientes'], 'required_permissions' => ['propietarios.view']],
            ['id' => 'citas', 'label' => 'Citas', 'url' => '/clinica/citas', 'aliases' => ['agenda', 'calendario'], 'required_permissions' => ['citas.view']],
            ['id' => 'historias', 'label' => 'Historias clínicas', 'url' => '/clinica/historias-clinicas', 'aliases' => ['consultas', 'historia clinica', 'historias clinicas'], 'required_permissions' => ['historias-clinicas.view']],
            ['id' => 'vacunaciones', 'label' => 'Vacunaciones', 'url' => '/clinica/vacunaciones', 'aliases' => ['vacunas', 'vacunación', 'vacunacion', 'desparasitación', 'desparasitacion'], 'required_permissions' => ['vacunaciones.view']],
            ['id' => 'laboratorio', 'label' => 'Laboratorio', 'url' => '/clinica/laboratorio', 'aliases' => ['lab', 'análisis', 'analisis'], 'required_permissions' => ['laboratorio.view']],
            ['id' => 'cirugias', 'label' => 'Cirugías', 'url' => '/clinica/cirugias', 'aliases' => ['cirugía', 'cirugia', 'quirófano', 'quirofano'], 'required_permissions' => ['cirugias.view']],
            ['id' => 'hospitalizacion', 'label' => 'Hospitalización', 'url' => '/clinica/hospitalizacion', 'aliases' => ['internamiento', 'hospital'], 'required_permissions' => ['hospitalizacion.view']],
            ['id' => 'recetas', 'label' => 'Recetas', 'url' => '/clinica/recetas', 'aliases' => ['prescripciones'], 'required_permissions' => ['recetas.view']],
            ['id' => 'grooming', 'label' => 'Grooming', 'url' => '/servicios/grooming', 'aliases' => ['baño', 'bano', 'peluquería', 'peluqueria'], 'required_permissions' => ['grooming.view']],
            ['id' => 'hotel', 'label' => 'Hotel', 'url' => '/servicios/hotel', 'aliases' => ['guardería', 'guarderia'], 'required_permissions' => ['hotel.view']],
            ['id' => 'caja', 'label' => 'Sesiones de caja', 'url' => '/caja/sesiones', 'aliases' => ['caja', 'sesión de caja', 'sesion de caja', 'abrir caja'], 'required_permissions' => ['caja-sesiones.view']],
            ['id' => 'ventas', 'label' => 'Ventas', 'url' => '/caja/ventas', 'aliases' => ['venta', 'pos', 'punto de venta'], 'required_permissions' => ['ventas.view']],
            ['id' => 'productos', 'label' => 'Productos', 'url' => '/inventario/productos', 'aliases' => ['inventario', 'catálogo', 'catalogo'], 'required_permissions' => ['productos.view']],
            ['id' => 'categorias-inventario', 'label' => 'Categorías de inventario', 'url' => '/inventario/categorias', 'aliases' => ['categorías', 'categorias'], 'required_permissions' => ['categorias-inventario.view']],
            ['id' => 'stock', 'label' => 'Stock', 'url' => '/inventario/stock', 'aliases' => ['existencias', 'almacén', 'almacen'], 'required_permissions' => ['stock.view']],
            ['id' => 'movimientos-stock', 'label' => 'Movimientos de stock', 'url' => '/inventario/movimientos', 'aliases' => ['kardex', 'movimientos'], 'required_permissions' => ['movimientos-stock.view']],
            ['id' => 'compras', 'label' => 'Compras', 'url' => '/inventario/compras', 'aliases' => ['compra', 'proveedores compra'], 'required_permissions' => ['compras.view']],
            ['id' => 'alertas-stock', 'label' => 'Alertas de stock', 'url' => '/inventario/alertas', 'aliases' => ['alertas inventario', 'caducidades', 'vencimientos', 'lotes por vencer'], 'required_permissions' => ['alertas-stock.view']],
            ['id' => 'proveedores', 'label' => 'Proveedores', 'url' => '/inventario/proveedores', 'aliases' => ['proveedor'], 'required_permissions' => ['proveedores.view']],
            ['id' => 'tarifas', 'label' => 'Tarifas', 'url' => '/configuracion/tarifas', 'aliases' => ['precios', 'servicios clínicos', 'servicios clinicos'], 'required_permissions' => ['tarifas.view']],
            ['id' => 'sedes', 'label' => 'Sedes', 'url' => '/configuracion/sedes', 'aliases' => ['sucursales'], 'required_permissions' => ['sedes.view']],
            ['id' => 'usuarios', 'label' => 'Usuarios', 'url' => '/configuracion/usuarios', 'aliases' => ['personal', 'empleados'], 'required_permissions' => ['usuarios.view']],
            ['id' => 'roles', 'label' => 'Roles', 'url' => '/configuracion/roles', 'aliases' => ['perfiles', 'permisos'], 'required_permissions' => ['roles.view']],
            ['id' => 'config-general', 'label' => 'Configuración general', 'url' => '/configuracion/general', 'aliases' => ['configuración', 'ajustes'], 'required_permissions' => ['config-general.view']],
            ['id' => 'suscripcion', 'label' => 'Suscripción', 'url' => '/configuracion/suscripcion', 'aliases' => ['plan', 'renovación'], 'required_permissions' => ['config-general.view']],
            ['id' => 'horarios', 'label' => 'Horarios', 'url' => '/configuracion/horarios', 'aliases' => ['disponibilidad'], 'required_permissions' => ['horarios.view']],
            ['id' => 'bloqueos', 'label' => 'Bloqueos', 'url' => '/configuracion/bloqueos', 'aliases' => ['indisponibilidad'], 'required_permissions' => ['bloqueos.view']],
            ['id' => 'pagos', 'label' => 'Pagos', 'url' => '/caja/pagos', 'aliases' => ['medios de pago'], 'required_permissions' => ['pagos.view']],
            ['id' => 'descuentos', 'label' => 'Descuentos', 'url' => '/caja/descuentos', 'aliases' => ['promociones'], 'required_permissions' => ['descuentos.view']],
            ['id' => 'documentos', 'label' => 'Documentos', 'url' => '/facturacion/documentos', 'aliases' => ['comprobantes', 'facturación'], 'required_permissions' => ['documentos.view']],
            ['id' => 'series', 'label' => 'Series', 'url' => '/facturacion/series', 'aliases' => ['correlativos'], 'required_permissions' => ['series.view']],
            ['id' => 'notas-baja', 'label' => 'Notas de baja', 'url' => '/facturacion/notas-baja', 'aliases' => ['bajas'], 'required_permissions' => ['notas-baja.view']],
            ['id' => 'resumenes', 'label' => 'Resúmenes', 'url' => '/facturacion/resumenes', 'aliases' => ['resumen diario'], 'required_permissions' => ['resumenes.view']],
            ['id' => 'comunicaciones-cola', 'label' => 'Cola de comunicaciones', 'url' => '/comunicaciones/cola', 'aliases' => ['cola', 'mensajes pendientes'], 'required_permissions' => ['comunicaciones-cola.view']],
            ['id' => 'comunicaciones-historico', 'label' => 'Histórico de comunicaciones', 'url' => '/comunicaciones/historico', 'aliases' => ['mensajes enviados'], 'required_permissions' => ['comunicaciones-historico.view']],
            ['id' => 'comunicaciones-bot-ia', 'label' => 'Bot IA', 'url' => '/comunicaciones/bot-ia', 'aliases' => ['bot ia', 'asistente whatsapp'], 'required_permissions' => ['comunicaciones-bot-ia.view']],
            ['id' => 'plantillas', 'label' => 'Plantillas', 'url' => '/comunicaciones/plantillas', 'aliases' => ['plantillas de mensajes'], 'required_permissions' => ['plantillas.view']],
            ['id' => 'ayuda', 'label' => 'Centro de ayuda', 'url' => '/configuracion/ayuda', 'aliases' => ['help', 'manual'], 'required_permissions' => ['in-app-assistant.use']],
        ];

        if ($user === null) {
            return $destinations;
        }

        return array_values(array_filter(
            $destinations,
            static fn (array $destination): bool => self::isAuthorized($destination, $user),
        ));
    }

    /**
     * @return array{id: string, label: string, url: string}|null
     */
    public static function resolve(string $query, ?User $user = null): ?array
    {
        $q = mb_strtolower(trim($query));
        $q = preg_replace('/\s+/u', ' ', $q) ?? $q;
        if ($q === '') {
            return null;
        }

        // Quitar prefijos típicos de pedido de navegación.
        $q = preg_replace(
            '/^(ll[eé]vame a|ir a|ve a|abre|abrir|abrir la|abrir el|dónde est[aá]|donde esta|dónde queda|donde queda|muéstrame|muestrame)\s+/u',
            '',
            $q,
        ) ?? $q;
        $q = trim($q, " \t\n\r\0\x0B¿?¡!");

        $best = null;
        $bestScore = 0;

        foreach (self::destinations($user) as $dest) {
            $candidates = array_merge([$dest['id'], mb_strtolower($dest['label'])], $dest['aliases']);
            foreach ($candidates as $alias) {
                $alias = mb_strtolower(trim($alias));
                if ($alias === '') {
                    continue;
                }
                if ($q === $alias) {
                    return [
                        'id' => $dest['id'],
                        'label' => $dest['label'],
                        'url' => $dest['url'],
                    ];
                }
                if (str_contains($q, $alias) || str_contains($alias, $q)) {
                    $score = mb_strlen($alias);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = [
                            'id' => $dest['id'],
                            'label' => $dest['label'],
                            'url' => $dest['url'],
                        ];
                    }
                }
            }
        }

        return $best;
    }

    public static function allowsUrl(string $url, User $user): bool
    {
        $destination = self::destinationForUrl($url, self::destinations());
        if ($destination === null) {
            return false;
        }

        return self::isAuthorized($destination, $user);
    }

    public static function isKnownKnowledgeUrl(string $url, string $scope): bool
    {
        if ($scope === 'clinic') {
            return self::destinationForUrl($url, self::destinations()) !== null;
        }

        if ($scope === 'platform') {
            return self::destinationForUrl($url, self::platformDestinations()) !== null;
        }

        return $scope === 'both'
            && (self::isKnownKnowledgeUrl($url, 'clinic') || self::isKnownKnowledgeUrl($url, 'platform'));
    }

    /**
     * Las acciones provenientes de conocimiento se validan contra permisos
     * canónicos del catálogo, nunca solo contra el JSON editable.
     */
    public static function allowsKnowledgeUrl(string $url, string $scope, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($scope === 'platform') {
            return $user->isPlatformSuperadmin()
                && self::destinationForUrl($url, self::platformDestinations()) !== null;
        }

        if ($scope !== 'clinic') {
            return false;
        }

        $destination = self::destinationForUrl($url, self::destinations());

        return $destination !== null && self::isAuthorized($destination, $user);
    }

    /**
     * @param  array{required_permissions: list<string>}  $destination
     */
    private static function isAuthorized(array $destination, User $user): bool
    {
        if ($user->isPlatformSuperadmin()) {
            return true;
        }

        foreach ($destination['required_permissions'] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{id: string, label: string, url: string, aliases: list<string>, required_permissions: list<string>}>
     */
    private static function platformDestinations(): array
    {
        return [
            ['id' => 'operaciones', 'label' => 'Operaciones', 'url' => '/plataforma/operaciones', 'aliases' => [], 'required_permissions' => []],
            ['id' => 'tenants', 'label' => 'Clínicas', 'url' => '/plataforma/tenants', 'aliases' => [], 'required_permissions' => []],
            ['id' => 'planes', 'label' => 'Planes', 'url' => '/plataforma/planes', 'aliases' => [], 'required_permissions' => []],
            ['id' => 'suscripciones', 'label' => 'Suscripciones', 'url' => '/plataforma/suscripciones', 'aliases' => [], 'required_permissions' => []],
            ['id' => 'avisos-renovacion', 'label' => 'Avisos de renovación', 'url' => '/plataforma/avisos-renovacion', 'aliases' => [], 'required_permissions' => []],
            ['id' => 'cobros', 'label' => 'Cobros', 'url' => '/plataforma/cobros', 'aliases' => [], 'required_permissions' => []],
            ['id' => 'pagos-plataforma', 'label' => 'Pagos', 'url' => '/plataforma/pagos', 'aliases' => [], 'required_permissions' => []],
            ['id' => 'salesbot-conversations', 'label' => 'Conversaciones SalesBot', 'url' => '/plataforma/salesbot-conversations', 'aliases' => [], 'required_permissions' => []],
            ['id' => 'salesbot-knowledge', 'label' => 'Conocimiento SalesBot', 'url' => '/plataforma/salesbot-knowledge', 'aliases' => [], 'required_permissions' => []],
            ['id' => 'in-app-assistant-knowledge', 'label' => 'Guías internas', 'url' => '/plataforma/in-app-assistant-knowledge', 'aliases' => [], 'required_permissions' => []],
            ['id' => 'bot-ia-announcements', 'label' => 'Novedades Bot IA', 'url' => '/plataforma/bot-ia-announcements', 'aliases' => [], 'required_permissions' => []],
            ['id' => 'configuracion-plataforma', 'label' => 'Configuración', 'url' => '/plataforma/configuracion', 'aliases' => [], 'required_permissions' => []],
        ];
    }

    /**
     * @param  list<array{id: string, label: string, url: string, aliases: list<string>, required_permissions: list<string>}>  $destinations
     * @return array{id: string, label: string, url: string, aliases: list<string>, required_permissions: list<string>}|null
     */
    private static function destinationForUrl(string $url, array $destinations): ?array
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '' || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $path) === 1) {
            return null;
        }

        foreach ($destinations as $destination) {
            $base = rtrim($destination['url'], '/');
            if ($path === $base || str_starts_with($path, $base.'/')) {
                return $destination;
            }
        }

        return null;
    }

    public static function looksLikeNavigationRequest(string $message): bool
    {
        $msg = mb_strtolower(trim($message));

        return (bool) preg_match(
            '/\b(ll[eé]vame|ir a|ve a|abre|abrir|dónde est[aá]|donde esta|dónde queda|donde queda)\b/u',
            $msg,
        );
    }
}
