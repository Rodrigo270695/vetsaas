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
                    'description' => 'Devuelve un resumen rápido: citas de hoy, ventas del día, alertas de stock bajo y pacientes recientes. Solo lectura.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
        ];
    }
}
