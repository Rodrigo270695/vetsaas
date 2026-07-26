<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConsultaHistoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('historias-clinicas.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'atendido_at' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:5000'],
            'subjetivo' => ['nullable', 'string', 'max:20000'],
            'objetivo' => ['nullable', 'string', 'max:20000'],
            'analisis' => ['nullable', 'string', 'max:20000'],
            'plan' => ['nullable', 'string', 'max:20000'],
            'medico_tratante' => ['nullable', 'string', 'max:200'],
            'peso_kg' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'temperatura_c' => ['nullable', 'numeric', 'min:30', 'max:45'],
            'fc_lpm' => ['nullable', 'integer', 'min:20', 'max:400'],
            'fr_rpm' => ['nullable', 'integer', 'min:5', 'max:120'],
            'examenes' => ['nullable', 'array', 'max:40'],
            'examenes.*.servicio_clinico_id' => ['nullable', 'uuid', Rule::exists('servicios_clinicos', 'id')],
            'examenes.*.nombre' => ['required', 'string', 'max:500'],
            'terapia_lineas' => ['nullable', 'array', 'max:40'],
            'terapia_lineas.*.farmaco_id' => ['nullable', 'uuid', Rule::exists('farmacos', 'id')],
            'terapia_lineas.*.farmaco_nombre' => ['required', 'string', 'max:200'],
            'terapia_lineas.*.dosis_volumen' => ['nullable', 'string', 'max:200'],
        ];
    }
}
