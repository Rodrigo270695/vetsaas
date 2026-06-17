<?php

namespace App\Support\Grooming;

use App\Grooming\GroomingCatalogoMode;
use App\Grooming\GroomingCatalogoServicio;
use App\Models\GroomingServicio;
use Illuminate\Validation\Rule;

final class GroomingTurnoServicioRules
{
    /**
     * @return array<string, mixed>
     */
    public static function servicioFields(): array
    {
        if (GroomingCatalogoMode::usaCatalogoPersonalizado()) {
            return [
                'grooming_servicio_id' => [
                    'required',
                    'uuid',
                    Rule::exists('grooming_servicios', 'id')->where(fn ($q) => $q->where('activo', true)),
                ],
                'servicio' => ['prohibited'],
                'servicio_detalle' => ['prohibited'],
            ];
        }

        return [
            'grooming_servicio_id' => ['prohibited'],
            'servicio' => ['required', 'string', Rule::in(GroomingCatalogoServicio::slugs())],
            'servicio_detalle' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn () => request()->input('servicio') === GroomingCatalogoServicio::OTRO_PERSONALIZADO),
                Rule::when(
                    request()->input('servicio') === GroomingCatalogoServicio::OTRO_PERSONALIZADO,
                    ['min:3'],
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizarParaPersistencia(array $data): array
    {
        if (! GroomingCatalogoMode::usaCatalogoPersonalizado()) {
            unset($data['grooming_servicio_id']);

            return $data;
        }

        $servicio = GroomingServicio::query()->findOrFail($data['grooming_servicio_id']);
        $data['servicio'] = $servicio->id;
        $data['grooming_servicio_id'] = $servicio->id;
        $data['servicio_detalle'] = null;

        if (! isset($data['duracion_minutos']) || (int) $data['duracion_minutos'] < 1) {
            $data['duracion_minutos'] = $servicio->duracion_minutos;
        }

        return $data;
    }
}
