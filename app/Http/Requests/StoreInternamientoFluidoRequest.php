<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInternamientoFluidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hospitalizacion.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $out = [];

        foreach (['tipo', 'solucion', 'via', 'volumen_ml', 'velocidad_ml_h', 'goteo_gtt_min', 'duracion_horas', 'aditivos'] as $key) {
            $value = $this->input($key);

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null) {
                $out[$key] = null;
            } elseif (is_string($value)) {
                $out[$key] = $value;
            }
        }

        if ($out !== []) {
            $this->merge($out);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'registrado_at' => ['required', 'date'],
            'tipo' => ['nullable', 'in:cristaloide,coloide,mantenimiento,otro'],
            'solucion' => ['nullable', 'string', 'max:120'],
            'via' => ['nullable', 'in:iv_periferica,iv_central,subcutanea,intraosea,oral'],
            'volumen_ml' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'velocidad_ml_h' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'goteo_gtt_min' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'duracion_horas' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'aditivos' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hayAlguno = false;

            foreach (['tipo', 'solucion', 'via', 'volumen_ml', 'velocidad_ml_h', 'goteo_gtt_min', 'duracion_horas', 'aditivos'] as $key) {
                if ($this->input($key) !== null && $this->input($key) !== '') {
                    $hayAlguno = true;
                    break;
                }
            }

            if (! $hayAlguno) {
                $validator->errors()->add('solucion', __('hospitalizacion.validation.fluido_vacio'));
            }
        });
    }
}
