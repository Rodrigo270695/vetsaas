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
    public static function definitions(): array
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
        ];
    }
}
