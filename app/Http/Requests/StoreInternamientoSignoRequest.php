<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInternamientoSignoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hospitalizacion.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $out = [];

        foreach ([
            'mucosas',
            'glucemia_mg_dl',
            'orina_ml',
            'vomito',
            'diarrea',
            'heces',
            'bristol',
            'alimento',
            'agua',
            'notas',
        ] as $key) {
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
            'mucosas' => ['nullable', 'string', 'max:80'],
            'glucemia_mg_dl' => ['nullable', 'numeric', 'min:0', 'max:1500'],
            'orina_ml' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'vomito' => ['nullable', 'in:no,si,alimentario,bilioso,hematico,espuma'],
            'diarrea' => ['nullable', 'in:no,si,moco,hemorragica,mixta'],
            'heces' => ['nullable', 'in:no,si,escasa,normal,abundante'],
            'bristol' => ['nullable', 'integer', 'min:1', 'max:7'],
            'alimento' => ['nullable', 'in:no,poco,normal,sonda'],
            'agua' => ['nullable', 'in:no,poco,normal'],
            'notas' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hayAlguno = false;

            foreach ([
                'mucosas',
                'glucemia_mg_dl',
                'orina_ml',
                'vomito',
                'diarrea',
                'heces',
                'bristol',
                'alimento',
                'agua',
                'notas',
            ] as $key) {
                if ($this->input($key) !== null && $this->input($key) !== '') {
                    $hayAlguno = true;
                    break;
                }
            }

            if (! $hayAlguno) {
                $validator->errors()->add('mucosas', __('hospitalizacion.validation.signo_vacio'));
            }
        });
    }
}
