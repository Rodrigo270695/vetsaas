<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoriaProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoria = $this->route('categoria');
        $categoriaId = is_object($categoria) && isset($categoria->id) ? (string) $categoria->id : null;

        return [
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('categorias_productos', 'id')->whereNull('deleted_at'),
                Rule::notIn(array_filter([$categoriaId])),
            ],
            'nombre' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('categorias_productos', 'slug')->ignore($categoriaId)],
            'descripcion' => ['nullable', 'string', 'max:20000'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = (string) $this->input('slug', '');
        $slug = trim(mb_strtolower($slug));

        $this->merge([
            'slug' => $slug === '' ? null : $slug,
            'orden' => $this->input('orden') === '' ? 0 : $this->input('orden', 0),
            'activo' => $this->boolean('activo'),
        ]);
    }
}
