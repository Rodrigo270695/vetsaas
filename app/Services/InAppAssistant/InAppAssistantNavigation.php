<?php

declare(strict_types=1);

namespace App\Services\InAppAssistant;

/**
 * Destinos de navegación conocidos para el asistente in-app.
 */
final class InAppAssistantNavigation
{
    /**
     * @return list<array{id: string, label: string, url: string, aliases: list<string>}>
     */
    public static function destinations(): array
    {
        return [
            ['id' => 'pacientes', 'label' => 'Pacientes', 'url' => '/clinica/pacientes', 'aliases' => ['paciente', 'mascotas', 'historial']],
            ['id' => 'propietarios', 'label' => 'Propietarios', 'url' => '/clinica/propietarios', 'aliases' => ['dueños', 'duenos', 'titulares', 'clientes']],
            ['id' => 'citas', 'label' => 'Citas', 'url' => '/clinica/citas', 'aliases' => ['agenda', 'calendario']],
            ['id' => 'historias', 'label' => 'Historias clínicas', 'url' => '/clinica/historias-clinicas', 'aliases' => ['consultas', 'historia clinica', 'historias clinicas']],
            ['id' => 'vacunaciones', 'label' => 'Vacunaciones', 'url' => '/clinica/vacunaciones', 'aliases' => ['vacunas', 'vacunación', 'vacunacion', 'desparasitación', 'desparasitacion']],
            ['id' => 'laboratorio', 'label' => 'Laboratorio', 'url' => '/clinica/laboratorio', 'aliases' => ['lab', 'análisis', 'analisis']],
            ['id' => 'cirugias', 'label' => 'Cirugías', 'url' => '/clinica/cirugias', 'aliases' => ['cirugía', 'cirugia', 'quirófano', 'quirofano']],
            ['id' => 'hospitalizacion', 'label' => 'Hospitalización', 'url' => '/clinica/hospitalizacion', 'aliases' => ['internamiento', 'hospital']],
            ['id' => 'recetas', 'label' => 'Recetas', 'url' => '/clinica/recetas', 'aliases' => ['prescripciones']],
            ['id' => 'grooming', 'label' => 'Grooming', 'url' => '/servicios/grooming', 'aliases' => ['baño', 'bano', 'peluquería', 'peluqueria']],
            ['id' => 'hotel', 'label' => 'Hotel', 'url' => '/servicios/hotel', 'aliases' => ['guardería', 'guarderia']],
            ['id' => 'caja', 'label' => 'Sesiones de caja', 'url' => '/caja/sesiones', 'aliases' => ['caja', 'sesión de caja', 'sesion de caja', 'abrir caja']],
            ['id' => 'ventas', 'label' => 'Ventas', 'url' => '/caja/ventas', 'aliases' => ['venta', 'pos', 'punto de venta']],
            ['id' => 'productos', 'label' => 'Productos', 'url' => '/inventario/productos', 'aliases' => ['inventario', 'catálogo', 'catalogo']],
            ['id' => 'stock', 'label' => 'Stock', 'url' => '/inventario/stock', 'aliases' => ['existencias', 'almacén', 'almacen']],
            ['id' => 'compras', 'label' => 'Compras', 'url' => '/inventario/compras', 'aliases' => ['compra', 'proveedores compra']],
            ['id' => 'alertas-stock', 'label' => 'Alertas de stock', 'url' => '/inventario/alertas', 'aliases' => ['alertas inventario']],
            ['id' => 'tarifas', 'label' => 'Tarifas', 'url' => '/configuracion/tarifas', 'aliases' => ['precios', 'servicios clínicos', 'servicios clinicos']],
            ['id' => 'sedes', 'label' => 'Sedes', 'url' => '/configuracion/sedes', 'aliases' => ['sucursales']],
            ['id' => 'usuarios', 'label' => 'Usuarios', 'url' => '/configuracion/usuarios', 'aliases' => ['personal', 'empleados']],
            ['id' => 'ayuda', 'label' => 'Centro de ayuda', 'url' => '/configuracion/ayuda', 'aliases' => ['help', 'manual']],
        ];
    }

    /**
     * @return array{id: string, label: string, url: string}|null
     */
    public static function resolve(string $query): ?array
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

        foreach (self::destinations() as $dest) {
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

    public static function looksLikeNavigationRequest(string $message): bool
    {
        $msg = mb_strtolower(trim($message));

        return (bool) preg_match(
            '/\b(ll[eé]vame|ir a|ve a|abre|abrir|dónde est[aá]|donde esta|dónde queda|donde queda)\b/u',
            $msg,
        );
    }
}
