<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClinicaAsesoradaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:200'],
            'ruc' => ['nullable', 'string', 'max:11', 'regex:/^\d{0,11}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'distrito_id' => ['nullable', 'integer', 'exists:distritos,id'],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'ruc' => 'RUC',
            'direccion' => 'dirección',
            'distrito_id' => 'distrito',
            'activo' => 'estado',
        ];
    }

    protected function prepareForValidation(): void
    {
        $ruc = preg_replace('/\D+/', '', (string) $this->input('ruc', '')) ?: null;

        $this->merge([
            'nombre' => trim((string) $this->input('nombre', '')),
            'ruc' => $ruc === '' || $ruc === null ? null : $ruc,
            'direccion' => trim((string) $this->input('direccion', '')) ?: null,
            'distrito_id' => $this->filled('distrito_id') ? $this->integer('distrito_id') : null,
            'activo' => $this->boolean('activo'),
        ]);
    }
}
