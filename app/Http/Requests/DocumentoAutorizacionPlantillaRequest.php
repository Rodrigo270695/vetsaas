<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Clinica\DocumentoAutorizacionRenderer;
use Illuminate\Foundation\Http\FormRequest;

class DocumentoAutorizacionPlantillaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('config-general.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'cuerpo' => ['required', 'string', 'max:50000'],
            'activo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $cuerpo = $this->input('cuerpo');
        $this->merge([
            'activo' => $this->boolean('activo'),
            'descripcion' => is_string($this->descripcion) && trim($this->descripcion) === ''
                ? null
                : $this->descripcion,
            'cuerpo' => is_string($cuerpo)
                ? DocumentoAutorizacionRenderer::sanitizeHtml($cuerpo)
                : $cuerpo,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $plain = trim(html_entity_decode(strip_tags((string) $this->input('cuerpo', '')), ENT_QUOTES, 'UTF-8'));
            if ($plain === '') {
                $validator->errors()->add('cuerpo', 'El texto del documento es obligatorio.');
            }
        });
    }
}
