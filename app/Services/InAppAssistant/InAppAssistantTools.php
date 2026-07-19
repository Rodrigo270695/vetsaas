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
        ];
    }
}
