<?php

namespace App\Http\Requests;

use App\Models\Cita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RescheduleCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('citas.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inicio_at' => ['required', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'inicio_at.after' => __('citas.validation.inicio_pasado'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Cita|null $cita */
            $cita = $this->route('cita');
            if (! $cita instanceof Cita) {
                return;
            }

            if (! in_array($cita->estado, [Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA], true)) {
                $validator->errors()->add('inicio_at', __('citas.reschedule.estado_bloqueado'));
            }
        });
    }
}
