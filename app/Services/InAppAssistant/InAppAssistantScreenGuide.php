<?php

declare(strict_types=1);

namespace App\Services\InAppAssistant;

/**
 * Explica la pantalla actual del usuario a partir de URL / componente Inertia.
 */
final class InAppAssistantScreenGuide
{
    /**
     * @param  array{url?: string, component?: string, paciente_id?: string, scope?: string}|null  $pageContext
     * @return array<string, mixed>
     */
    public static function explain(?array $pageContext, string $scope = 'clinic'): array
    {
        $url = trim((string) ($pageContext['url'] ?? ''));
        $component = trim((string) ($pageContext['component'] ?? ''));
        $pacienteId = trim((string) ($pageContext['paciente_id'] ?? ''));
        $path = self::pathFromUrl($url);

        $match = $scope === 'platform'
            ? self::matchPlatform($path, $component)
            : self::matchClinic($path, $component, $pacienteId !== '');

        if ($match === null) {
            return [
                'ok' => true,
                'known' => false,
                'url' => $url !== '' ? $url : null,
                'component' => $component !== '' ? $component : null,
                'titulo' => 'Pantalla actual',
                'resumen' => $scope === 'platform'
                    ? 'Estás en el panel central de VetSaaS. Pregúntame por cobros, suscripciones, clínicas, WhatsApp o leads fríos.'
                    : 'Estás en VetSaaS. Pregúntame qué hace esta pantalla o cómo llegar a otro módulo.',
                'acciones_tipicas' => [],
            ];
        }

        return [
            'ok' => true,
            'known' => true,
            'url' => $url !== '' ? $url : ($match['url'] ?? null),
            'component' => $component !== '' ? $component : null,
            'id' => $match['id'],
            'titulo' => $match['titulo'],
            'resumen' => $match['resumen'],
            'acciones_tipicas' => $match['acciones'],
            'paciente_en_contexto' => $pacienteId !== '',
        ];
    }

    private static function pathFromUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = explode('?', $url, 2)[0];
        }

        return '/'.trim($path, '/');
    }

    /**
     * @return array{id: string, titulo: string, resumen: string, acciones: list<string>, url?: string}|null
     */
    private static function matchClinic(string $path, string $component, bool $hasPatient): ?array
    {
        $rules = [
            [
                'id' => 'paciente-historial',
                'match' => fn (): bool => $hasPatient || str_contains($path, '/clinica/pacientes/') || str_contains($component, 'pacientes/'),
                'titulo' => 'Historial del paciente',
                'resumen' => 'Ficha e historial clínico de la mascota abierta: consultas, vacunas, labs y datos del titular.',
                'acciones' => [
                    'Revisa la línea de tiempo clínica',
                    'Registra una consulta o aplicación',
                    'Abre el PDF del historial o compártelo',
                ],
                'url' => $path !== '' ? $path : '/clinica/pacientes',
            ],
            [
                'id' => 'citas',
                'match' => fn (): bool => str_starts_with($path, '/clinica/citas') || str_contains($component, 'citas'),
                'titulo' => 'Agenda de citas',
                'resumen' => 'Calendario y listado de citas por sede y veterinario. Aquí programas, confirmas o cancelas turnos.',
                'acciones' => ['Crear cita', 'Filtrar por veterinario o sede', 'Cambiar estado de una cita'],
                'url' => '/clinica/citas',
            ],
            [
                'id' => 'caja-sesiones',
                'match' => fn (): bool => str_starts_with($path, '/caja/sesiones') || str_contains($component, 'caja/sesiones'),
                'titulo' => 'Sesiones de caja',
                'resumen' => 'Apertura y cierre de caja por sede: saldo inicial, ventas de la sesión y cierre de efectivo.',
                'acciones' => ['Abrir tu sesión', 'Ver sesiones abiertas', 'Cerrar caja al final del turno'],
                'url' => '/caja/sesiones',
            ],
            [
                'id' => 'ventas',
                'match' => fn (): bool => str_starts_with($path, '/caja/ventas') || str_contains($component, 'caja/ventas') || str_contains($component, 'ventas'),
                'titulo' => 'Ventas / POS',
                'resumen' => 'Punto de venta y listado de boletas/facturas. Busca por número, FEL o titular.',
                'acciones' => ['Registrar una venta', 'Buscar boleta por número', 'Ver detalle y comprobante'],
                'url' => '/caja/ventas',
            ],
            [
                'id' => 'alertas-stock',
                'match' => fn (): bool => str_starts_with($path, '/inventario/alertas') || str_contains($component, 'inventario/alertas'),
                'titulo' => 'Alertas de inventario',
                'resumen' => 'Stock agotado o bajo mínimo, y lotes vencidos o por vencer.',
                'acciones' => ['Filtrar por stock o caducidades', 'Revisar lotes por sede', 'Ir al producto para ajustar'],
                'url' => '/inventario/alertas',
            ],
            [
                'id' => 'stock',
                'match' => fn (): bool => str_starts_with($path, '/inventario/stock') || str_contains($component, 'inventario/stock'),
                'titulo' => 'Stock por sede',
                'resumen' => 'Existencias actuales de productos en cada sede.',
                'acciones' => ['Buscar producto', 'Ver existencias por sede', 'Ajustar stock si tienes permiso'],
                'url' => '/inventario/stock',
            ],
            [
                'id' => 'productos',
                'match' => fn (): bool => str_starts_with($path, '/inventario/productos') || str_contains($component, 'inventario/productos'),
                'titulo' => 'Catálogo de productos',
                'resumen' => 'Alta y edición de productos, SKU, precios y stock mínimo.',
                'acciones' => ['Crear producto', 'Editar stock mínimo', 'Ver ficha del ítem'],
                'url' => '/inventario/productos',
            ],
            [
                'id' => 'vacunaciones',
                'match' => fn (): bool => str_starts_with($path, '/clinica/vacunaciones') || str_contains($component, 'vacunaciones'),
                'titulo' => 'Vacunaciones',
                'resumen' => 'Registro y seguimiento de vacunas y desparasitaciones, con próximas dosis sugeridas.',
                'acciones' => ['Registrar aplicación', 'Ver próximas dosis', 'Abrir el paciente'],
                'url' => '/clinica/vacunaciones',
            ],
            [
                'id' => 'pacientes',
                'match' => fn (): bool => str_starts_with($path, '/clinica/pacientes') || str_contains($component, 'pacientes'),
                'titulo' => 'Pacientes',
                'resumen' => 'Listado de mascotas de la clínica. Desde aquí entras al historial de cada una.',
                'acciones' => ['Buscar por nombre o microchip', 'Registrar paciente', 'Abrir historial'],
                'url' => '/clinica/pacientes',
            ],
            [
                'id' => 'propietarios',
                'match' => fn (): bool => str_starts_with($path, '/clinica/propietarios') || str_contains($component, 'propietarios'),
                'titulo' => 'Propietarios',
                'resumen' => 'Titulares/dueños: datos de contacto y mascotas asociadas.',
                'acciones' => ['Buscar por documento o teléfono', 'Crear titular', 'Ver sus mascotas'],
                'url' => '/clinica/propietarios',
            ],
            [
                'id' => 'dashboard',
                'match' => fn (): bool => $path === '/' || $path === '/dashboard' || str_contains($component, 'dashboard'),
                'titulo' => 'Inicio / dashboard',
                'resumen' => 'Resumen operativo de la clínica: accesos rápidos y señales del día.',
                'acciones' => ['Revisar citas de hoy', 'Ir a caja o inventario', 'Preguntarme por alertas'],
                'url' => '/dashboard',
            ],
        ];

        foreach ($rules as $rule) {
            if (($rule['match'])()) {
                return [
                    'id' => $rule['id'],
                    'titulo' => $rule['titulo'],
                    'resumen' => $rule['resumen'],
                    'acciones' => $rule['acciones'],
                    'url' => $rule['url'] ?? null,
                ];
            }
        }

        $nav = InAppAssistantNavigation::resolve(ltrim($path, '/'));
        if ($nav !== null) {
            return [
                'id' => $nav['id'],
                'titulo' => $nav['label'],
                'resumen' => "Módulo «{$nav['label']}» de VetSaaS.",
                'acciones' => ['Explora las acciones de la pantalla', 'Pregúntame cómo usar este módulo'],
                'url' => $nav['url'],
            ];
        }

        return null;
    }

    /**
     * @return array{id: string, titulo: string, resumen: string, acciones: list<string>, url?: string}|null
     */
    private static function matchPlatform(string $path, string $component): ?array
    {
        $rules = [
            [
                'id' => 'cobros',
                'match' => fn (): bool => str_starts_with($path, '/plataforma/cobros') || str_contains($component, 'cobros'),
                'titulo' => 'Cobros',
                'resumen' => 'Pagos de suscripción: pendientes, fallidos y procesados.',
                'acciones' => ['Filtrar por estado', 'Ver detalle de un cobro', 'Revisar fallidos recientes'],
                'url' => '/plataforma/cobros',
            ],
            [
                'id' => 'pagos',
                'match' => fn (): bool => str_starts_with($path, '/plataforma/pagos'),
                'titulo' => 'Pagos',
                'resumen' => 'Vista de pagos procesados e historial de cobros exitosos.',
                'acciones' => ['Ver quiénes pagaron', 'Abrir historial de una clínica'],
                'url' => '/plataforma/pagos',
            ],
            [
                'id' => 'tenants',
                'match' => fn (): bool => str_starts_with($path, '/plataforma/tenants') || str_contains($component, 'tenants'),
                'titulo' => 'Clínicas (tenants)',
                'resumen' => 'Listado y ficha de clínicas del SaaS: estado, plan y datos comerciales.',
                'acciones' => ['Buscar clínica', 'Revisar suscripción', 'Ver estado operativo'],
                'url' => '/plataforma/tenants',
            ],
            [
                'id' => 'suscripciones',
                'match' => fn (): bool => str_starts_with($path, '/plataforma/suscripciones') || str_contains($component, 'suscripciones'),
                'titulo' => 'Suscripciones',
                'resumen' => 'Planes, próximos cobros, grace/suspended y add-on Bot IA.',
                'acciones' => ['Ver próximas a vencer', 'Revisar Bot IA activo', 'Abrir detalle'],
                'url' => '/plataforma/suscripciones',
            ],
            [
                'id' => 'operaciones',
                'match' => fn (): bool => str_starts_with($path, '/plataforma/operaciones') || str_contains($component, 'operaciones'),
                'titulo' => 'Operaciones',
                'resumen' => 'Radar de salud: WhatsApp/OpenWA, colas fallidas, señales de cobro y tenants.',
                'acciones' => ['Revisar sesión OpenWA', 'Ver jobs fallidos', 'Chequear clínicas con error WA'],
                'url' => '/plataforma/operaciones',
            ],
            [
                'id' => 'configuracion',
                'match' => fn (): bool => str_starts_with($path, '/plataforma/configuracion'),
                'titulo' => 'Configuración de plataforma',
                'resumen' => 'Ajustes globales del SaaS, novedades del asistente y límites.',
                'acciones' => ['Publicar novedades', 'Revisar límite del asistente'],
                'url' => '/plataforma/configuracion',
            ],
            [
                'id' => 'planes',
                'match' => fn (): bool => str_starts_with($path, '/plataforma/planes'),
                'titulo' => 'Planes',
                'resumen' => 'Catálogo de planes comerciales del SaaS.',
                'acciones' => ['Editar precios', 'Activar/desactivar planes'],
                'url' => '/plataforma/planes',
            ],
        ];

        foreach ($rules as $rule) {
            if (($rule['match'])()) {
                return [
                    'id' => $rule['id'],
                    'titulo' => $rule['titulo'],
                    'resumen' => $rule['resumen'],
                    'acciones' => $rule['acciones'],
                    'url' => $rule['url'] ?? null,
                ];
            }
        }

        return null;
    }
}
