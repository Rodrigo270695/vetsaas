<?php

declare(strict_types=1);

namespace App\Services\ClinicBot;

final class ClinicBotTools
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
                    'name' => 'obtener_fecha_actual',
                    'description' => 'Devuelve la fecha y hora actual en Perú (America/Lima). Úsala antes de interpretar hoy, mañana o pasado mañana.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass,
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'resolver_fecha',
                    'description' => 'Convierte expresiones como hoy, mañana, pasado mañana o un día de la semana a fecha YYYY-MM-DD según la hora actual en Perú.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'expresion' => [
                                'type' => 'string',
                                'description' => 'Ej: hoy, mañana, pasado mañana, lunes, 2026-06-30',
                            ],
                        ],
                        'required' => ['expresion'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_productos',
                    'description' => 'Lista productos activos del inventario de esta clínica (mismo tenant).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'busqueda' => [
                                'type' => 'string',
                                'description' => 'Texto opcional para filtrar por nombre o SKU.',
                            ],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_servicios_grooming',
                    'description' => 'Lista servicios de grooming/peluquería activos de esta clínica.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass,
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'listar_mascotas_cliente',
                    'description' => 'Lista las mascotas registradas del cliente según su número de WhatsApp.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass,
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'registrar_propietario',
                    'description' => 'Registra al propietario/tutor con su número de WhatsApp si aún no existe en la clínica.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nombres' => ['type' => 'string', 'description' => 'Nombres del propietario'],
                            'apellidos' => ['type' => 'string', 'description' => 'Apellidos (opcional)'],
                        ],
                        'required' => ['nombres'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'registrar_mascota',
                    'description' => 'Registra una mascota nueva vinculada al número de WhatsApp. Crea al propietario si no existe.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'nombre' => ['type' => 'string', 'description' => 'Nombre de la mascota'],
                            'especie' => ['type' => 'string', 'description' => 'Ej: perro, gato'],
                            'raza' => ['type' => 'string', 'description' => 'Raza (opcional)'],
                            'edad_anios' => ['type' => 'integer', 'description' => 'Edad aproximada en años'],
                            'propietario_nombres' => ['type' => 'string', 'description' => 'Nombres del tutor si no está registrado'],
                            'propietario_apellidos' => ['type' => 'string', 'description' => 'Apellidos del tutor (opcional)'],
                        ],
                        'required' => ['nombre'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'registrar_cita',
                    'description' => 'Registra una cita veterinaria para una mascota del cliente. Confirma mascota, fecha y hora antes de llamar.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'paciente_id' => ['type' => 'string', 'description' => 'UUID de la mascota'],
                            'fecha' => ['type' => 'string', 'description' => 'YYYY-MM-DD o hoy/mañana/pasado mañana'],
                            'hora' => ['type' => 'string', 'description' => 'Hora en formato 24 h, ej. 10:30'],
                            'motivo' => ['type' => 'string', 'description' => 'Motivo breve de la consulta'],
                            'duracion_minutos' => ['type' => 'integer', 'description' => 'Duración estimada, por defecto 30'],
                        ],
                        'required' => ['paciente_id', 'fecha', 'hora'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'registrar_turno_grooming',
                    'description' => 'Registra un turno de grooming. Usa el id del servicio devuelto por listar_servicios_grooming.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'paciente_id' => ['type' => 'string'],
                            'servicio_id' => ['type' => 'string', 'description' => 'ID o slug del servicio de grooming'],
                            'fecha' => ['type' => 'string'],
                            'hora' => ['type' => 'string'],
                            'duracion_minutos' => ['type' => 'integer'],
                        ],
                        'required' => ['paciente_id', 'servicio_id', 'fecha', 'hora'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];
    }
}
