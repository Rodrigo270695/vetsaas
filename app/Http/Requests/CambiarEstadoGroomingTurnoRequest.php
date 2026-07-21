<?php

namespace App\Http\Requests;

use App\Models\GroomingTurno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CambiarEstadoGroomingTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('grooming.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notificar_whatsapp')) {
            $v = $this->input('notificar_whatsapp');
            $this->merge([
                'notificar_whatsapp' => filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in([
                GroomingTurno::ESTADO_EN_PROCESO,
                GroomingTurno::ESTADO_COMPLETADA,
                GroomingTurno::ESTADO_CANCELADA,
                GroomingTurno::ESTADO_NO_ASISTIO,
            ])],
            'telefono' => ['nullable', 'string', 'max:20'],
            'notificar_whatsapp' => ['nullable', 'boolean'],
            'fotos' => ['nullable', 'array', 'max:8'],
            'fotos.*' => ['image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('estado')) {
                return;
            }

            $turno = $this->route('grooming_turno');
            if (! $turno instanceof GroomingTurno) {
                return;
            }

            $destino = (string) $this->input('estado');
            $permitidos = match ($turno->estado) {
                GroomingTurno::ESTADO_PROGRAMADA,
                GroomingTurno::ESTADO_CONFIRMADA => [
                    GroomingTurno::ESTADO_EN_PROCESO,
                    GroomingTurno::ESTADO_CANCELADA,
                    GroomingTurno::ESTADO_NO_ASISTIO,
                ],
                GroomingTurno::ESTADO_EN_PROCESO => [
                    GroomingTurno::ESTADO_COMPLETADA,
                    GroomingTurno::ESTADO_CANCELADA,
                    GroomingTurno::ESTADO_NO_ASISTIO,
                ],
                default => [],
            };

            if (! in_array($destino, $permitidos, true)) {
                $validator->errors()->add(
                    'estado',
                    'El turno ya no permite ese cambio de estado.',
                );
            }
        });
    }
}
