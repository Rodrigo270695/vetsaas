<?php

declare(strict_types=1);

namespace App\Services\InAppAssistant;

/**
 * Tools de solo lectura para el asistente interno del staff.
 */
final class InAppAssistantTools
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(string $scope = 'clinic'): array
    {
        return $scope === 'platform'
            ? self::platformDefinitions()
            : self::clinicDefinitions();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function clinicDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buscar_pacientes',
                    'description' => 'Busca pacientes (mascotas) por nombre, microchip o titular. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'q' => [
                                'type' => 'string',
                                'description' => 'Texto a buscar (nombre mascota, microchip o titular).',
                            ],
                        ],
                        'required' => ['q'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buscar_propietarios',
                    'description' => 'Busca propietarios/titulares por nombre, documento o teléfono. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'q' => [
                                'type' => 'string',
                                'description' => 'Texto a buscar.',
                            ],
                        ],
                        'required' => ['q'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buscar_productos',
                    'description' => 'Busca productos del inventario por nombre o SKU. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'q' => [
                                'type' => 'string',
                                'description' => 'Texto a buscar.',
                            ],
                        ],
                        'required' => ['q'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'resumen_operativo',
                    'description' => 'Resumen rápido: citas/ventas de hoy, stock bajo, caja abierta y vacunas próximas. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'alertas_operativas',
                    'description' => 'Alertas del día: vacunas/desparasitaciones por vencer, stock bajo y estado de sesiones de caja. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'dias' => [
                                'type' => 'integer',
                                'description' => 'Ventana de días para vacunas próximas (default 14, máx 60).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'paciente_en_contexto',
                    'description' => 'Detalle del paciente que el usuario está viendo ahora (si hay paciente_id en el contexto de pantalla). Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'resolver_navegacion',
                    'description' => 'Resuelve a qué pantalla de VetSaaS llevar al usuario (citas, vacunaciones, caja, stock, etc.). Devuelve URL interna. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'destino' => [
                                'type' => 'string',
                                'description' => 'Nombre del módulo o pantalla (ej. vacunaciones, caja, stock, pacientes).',
                            ],
                        ],
                        'required' => ['destino'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'resumen_historia_paciente',
                    'description' => 'Resumen corto del historial clínico: últimas consultas, vacunas/aplicaciones y pedidos de laboratorio. Usa paciente_id o el paciente en pantalla. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'paciente_id' => [
                                'type' => 'string',
                                'description' => 'UUID del paciente. Si se omite, usa el paciente de la pantalla actual.',
                            ],
                            'limite' => [
                                'type' => 'integer',
                                'description' => 'Cantidad de eventos por tipo (default 5, máx 10).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'agenda_citas',
                    'description' => 'Lista citas por fecha (hoy/mañana/YYYY-MM-DD), opcionalmente filtradas por nombre de veterinario y/o sede. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'fecha' => [
                                'type' => 'string',
                                'description' => 'hoy | mañana | YYYY-MM-DD. Default: hoy.',
                            ],
                            'veterinario' => [
                                'type' => 'string',
                                'description' => 'Nombre (o parte) del veterinario.',
                            ],
                            'sede' => [
                                'type' => 'string',
                                'description' => 'Nombre (o parte) de la sede.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'caducidades_proximas',
                    'description' => 'Lotes de inventario vencidos o por vencer (caducidades). Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'dias' => [
                                'type' => 'integer',
                                'description' => 'Ventana de días hacia adelante para por vencer (default 30, máx 90).',
                            ],
                            'limite' => [
                                'type' => 'integer',
                                'description' => 'Máximo de lotes a listar (default 15, máx 30).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'caja_del_dia',
                    'description' => 'Caja del día: sesiones abiertas, mi sesión, ventas de hoy y último cierre. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buscar_venta',
                    'description' => 'Busca una venta/boleta/factura por número interno o número FEL (serie-correlativo). Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'q' => [
                                'type' => 'string',
                                'description' => 'Número de venta, boleta FEL o parte del número.',
                            ],
                        ],
                        'required' => ['q'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'quien_atiende_hoy',
                    'description' => 'Veterinarios que tienen citas hoy (quién atiende), con conteo por estado. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'fecha' => [
                                'type' => 'string',
                                'description' => 'hoy | mañana | YYYY-MM-DD. Default: hoy.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'explicar_pantalla',
                    'description' => 'Explica qué hace la pantalla actual del usuario (según URL/componente) y acciones típicas. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Tools del portal central (superadmin): cobros, suscripciones, clínicas.
     *
     * @return list<array<string, mixed>>
     */
    public static function platformDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'cobros_pendientes',
                    'description' => 'Lista cobros/pagos de suscripción en estado pendiente (quién debe pagar). Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limite' => [
                                'type' => 'integer',
                                'description' => 'Máximo de filas (default 20, máx 40).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'cobros_fallidos',
                    'description' => 'Lista cobros fallidos recientes (pasarela rechazó). Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'dias' => [
                                'type' => 'integer',
                                'description' => 'Ventana en días (default 14, máx 60).',
                            ],
                            'limite' => [
                                'type' => 'integer',
                                'description' => 'Máximo de filas (default 20, máx 40).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'suscripciones_en_riesgo',
                    'description' => 'Clínicas en grace, suspended o con próximo cobro en los próximos días. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'dias_proximo_cobro' => [
                                'type' => 'integer',
                                'description' => 'Días hacia adelante para próximo cobro (default 7, máx 30).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'resumen_plataforma',
                    'description' => 'Resumen operativo del SaaS: conteos de cobros pendientes/fallidos, suscripciones en grace/suspended, próximo cobro. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buscar_clinicas',
                    'description' => 'Busca clínicas (tenants) por nombre comercial, razón social o slug. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'q' => [
                                'type' => 'string',
                                'description' => 'Texto a buscar.',
                            ],
                        ],
                        'required' => ['q'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'resolver_navegacion_plataforma',
                    'description' => 'Resuelve a qué pantalla del panel central llevar al superadmin (cobros, clínicas, planes, etc.). Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'destino' => [
                                'type' => 'string',
                                'description' => 'Nombre del módulo (cobros, clínicas, planes, configuración, operaciones).',
                            ],
                        ],
                        'required' => ['destino'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'tenants_por_vencer',
                    'description' => 'Clínicas/suscripciones cuyo próximo cobro o fin de periodo cae en los próximos X días. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'dias' => [
                                'type' => 'integer',
                                'description' => 'Días hacia adelante (default 7, máx 60).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'uso_bot_ia',
                    'description' => 'Resumen de clínicas con Bot IA activo vs inactivo (add-on). Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limite' => [
                                'type' => 'integer',
                                'description' => 'Máximo de clínicas a listar por grupo (default 20, máx 40).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'estado_whatsapp_openwa',
                    'description' => 'Estado de OpenWA (sesión plataforma), sesiones de tenants con error y jobs fallidos recientes. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'leads_frios',
                    'description' => 'Conteo y muestra de leads fríos elegibles para reactivación del SalesBot. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'dias_inactividad' => [
                                'type' => 'integer',
                                'description' => 'Días sin mensaje para considerar frío (default 3, máx 30).',
                            ],
                            'limite' => [
                                'type' => 'integer',
                                'description' => 'Muestra de leads (default 10, máx 25).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'explicar_pantalla',
                    'description' => 'Explica qué hace la pantalla actual del panel central (según URL/componente). Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
        ];
    }
}
