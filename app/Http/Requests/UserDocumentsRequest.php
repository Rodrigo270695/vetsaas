<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solo ficha documental del staff: colegiatura + CV / DNI / firma.
 */
class UserDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'colegiatura' => ['nullable', 'string', 'max:40'],
            'cv' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
            'dni_file' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'firma' => ['nullable', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
            'remove_cv' => ['sometimes', 'boolean'],
            'remove_dni_file' => ['sometimes', 'boolean'],
            'remove_firma' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'colegiatura' => 'número de colegiatura',
            'cv' => 'CV',
            'dni_file' => 'documento de identidad',
            'firma' => 'firma digital',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'colegiatura' => trim((string) $this->input('colegiatura', '')) ?: null,
            'remove_cv' => filter_var($this->input('remove_cv', false), FILTER_VALIDATE_BOOLEAN),
            'remove_dni_file' => filter_var($this->input('remove_dni_file', false), FILTER_VALIDATE_BOOLEAN),
            'remove_firma' => filter_var($this->input('remove_firma', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
