<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProveedorInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $proveedor = $this->route('proveedor');
        $proveedorId = is_object($proveedor) && isset($proveedor->id) ? (string) $proveedor->id : null;

        return [
            'ruc' => [
                'required',
                'string',
                'regex:/^[0-9]{11}$/',
                Rule::unique('proveedores', 'ruc')->ignore($proveedorId),
            ],
            'razon_social' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:20000'],
            'ubigeo_sunat' => ['nullable', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'estado_sunat' => ['nullable', 'string', 'max:32'],
            'condicion_sunat' => ['nullable', 'string', 'max:32'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'lowercase', 'max:255', 'email'],
            'notas' => ['nullable', 'string', 'max:20000'],
            'activo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $ruc = preg_replace('/\D+/', '', (string) $this->input('ruc', '')) ?? '';

        $this->merge([
            'ruc' => $ruc,
            'razon_social' => trim((string) $this->input('razon_social', '')),
            'direccion' => $this->nullableText('direccion'),
            'ubigeo_sunat' => $this->nullableDigits('ubigeo_sunat', 6),
            'estado_sunat' => $this->nullableString('estado_sunat', 32),
            'condicion_sunat' => $this->nullableString('condicion_sunat', 32),
            'telefono' => $this->nullableTrim('telefono', 40),
            'email' => $this->nullableEmail(),
            'notas' => $this->nullableText('notas'),
            'activo' => $this->boolean('activo'),
        ]);
    }

    private function nullableString(string $key, ?int $maxLen = null): ?string
    {
        $v = trim((string) $this->input($key, ''));
        if ($v === '') {
            return null;
        }

        if ($maxLen !== null) {
            $v = mb_substr($v, 0, $maxLen);
        }

        return $v;
    }

    private function nullableText(string $key): ?string
    {
        $v = trim((string) $this->input($key, ''));

        return $v === '' ? null : $v;
    }

    private function nullableTrim(string $key, int $maxLen): ?string
    {
        $v = trim((string) $this->input($key, ''));
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, $maxLen);
    }

    private function nullableEmail(): ?string
    {
        $v = mb_strtolower(trim((string) $this->input('email', '')));

        return $v === '' ? null : $v;
    }

    private function nullableDigits(string $key, int $len): ?string
    {
        $raw = preg_replace('/\D+/', '', (string) $this->input($key, '')) ?? '';
        if (strlen($raw) !== $len) {
            return null;
        }

        return $raw;
    }
}
