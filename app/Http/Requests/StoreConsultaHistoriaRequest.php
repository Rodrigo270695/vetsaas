<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsultaHistoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('historias-clinicas.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paciente_id' => ['required', 'uuid', Rule::exists('pacientes', 'id')->whereNull('deleted_at')],
            'cita_id' => [
                'nullable',
                'uuid',
                Rule::exists('citas', 'id')->whereNull('deleted_at'),
            ],
            'atendido_at' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:5000'],
            'subjetivo' => ['nullable', 'string', 'max:20000'],
            'objetivo' => ['nullable', 'string', 'max:20000'],
            'analisis' => ['nullable', 'string', 'max:20000'],
            'plan' => ['nullable', 'string', 'max:20000'],
            'peso_kg' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'temperatura_c' => ['nullable', 'numeric', 'min:30', 'max:45'],
            'fc_lpm' => ['nullable', 'integer', 'min:20', 'max:400'],
            'fr_rpm' => ['nullable', 'integer', 'min:5', 'max:120'],
        ];
    }
}
