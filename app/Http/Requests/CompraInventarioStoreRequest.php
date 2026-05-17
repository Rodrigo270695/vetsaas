<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompraInventarioStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('compras.create') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'sede_id' => [
                'required',
                'uuid',
                Rule::exists('sedes', 'id')->where('tenant_id', $tenantId)->where('activa', true)->whereNull('deleted_at'),
            ],
            'proveedor_id' => [
                'nullable',
                'uuid',
                Rule::exists('proveedores', 'id')->whereNull('deleted_at'),
            ],
            'fecha_documento' => ['required', 'date'],
            'numero_documento' => ['nullable', 'string', 'max:64'],
            'serie' => ['nullable', 'string', 'max:16'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:20000'],
            'factura' => ['nullable', 'file', 'mimes:pdf,jpeg,jpg,png', 'max:12288'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => [
                'required',
                'uuid',
                Rule::exists('productos', 'id')->whereNull('deleted_at'),
            ],
            'lineas.*.cantidad' => ['required', 'numeric', 'min:0.001'],
            'lineas.*.costo_unitario' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $proveedor = trim((string) $this->input('proveedor_id', ''));

        $this->merge([
            'proveedor_id' => $proveedor === '' ? null : $proveedor,
            'numero_documento' => $this->nullableTrim('numero_documento', 64),
            'serie' => $this->nullableTrim('serie', 16),
            'moneda' => strtoupper(trim((string) $this->input('moneda', 'PEN'))) ?: 'PEN',
            'notas' => $this->nullableText('notas'),
            'total' => $this->nullableNumericInput('total'),
        ]);

        $lineas = $this->input('lineas', []);
        if (is_array($lineas)) {
            foreach ($lineas as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $cu = $row['costo_unitario'] ?? null;
                if ($cu === '' || $cu === null) {
                    $lineas[$i]['costo_unitario'] = null;
                }
            }
            $this->merge(['lineas' => $lineas]);
        }
    }

    private function nullableTrim(string $key, int $maxLen): ?string
    {
        $v = trim((string) $this->input($key, ''));
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, $maxLen);
    }

    private function nullableText(string $key): ?string
    {
        $v = trim((string) $this->input($key, ''));

        return $v === '' ? null : $v;
    }

    private function nullableNumericInput(string $key): mixed
    {
        $v = $this->input($key);

        if ($v === null || $v === '') {
            return null;
        }

        return $v;
    }
}
