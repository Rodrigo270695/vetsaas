<?php

namespace App\Http\Requests;

use App\Models\MovimientoInventario;
use App\Models\Sede;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MovimientoInventarioStoreRequest extends FormRequest
{
    public const TIPO_TRASLADO = 'traslado';

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->tenant_id !== null
            && $user->can('movimientos-stock.create');
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        if ($tenantId === null) {
            return [
                'producto_id' => ['prohibited'],
                'sede_id' => ['prohibited'],
                'sede_destino_id' => ['prohibited'],
                'tipo' => ['prohibited'],
                'cantidad' => ['prohibited'],
                'notas' => ['prohibited'],
            ];
        }

        $tipos = [...MovimientoInventario::TIPOS_OPERATIVOS, self::TIPO_TRASLADO];

        return [
            'producto_id' => [
                'required',
                'uuid',
                Rule::exists('productos', 'id')->whereNull('deleted_at'),
            ],
            'sede_id' => [
                'required',
                'uuid',
                Rule::exists('sedes', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)
                        ->where('activa', true)
                        ->whereNull('deleted_at');
                }),
            ],
            'sede_destino_id' => [
                'nullable',
                'uuid',
                Rule::exists('sedes', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)
                        ->where('activa', true)
                        ->whereNull('deleted_at');
                }),
            ],
            'tipo' => ['required', 'string', Rule::in($tipos)],
            'cantidad' => ['required', 'numeric', 'min:0.001', 'max:99999999.999'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'numero_lote' => ['nullable', 'string', 'max:128'],
            'fecha_vencimiento' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('tipo') !== self::TIPO_TRASLADO) {
                return;
            }

            $tenantId = $this->user()?->tenant_id;
            $sedesCount = $tenantId === null
                ? 0
                : Sede::query()
                    ->where('tenant_id', $tenantId)
                    ->where('activa', true)
                    ->whereNull('deleted_at')
                    ->count();

            if ($sedesCount < 2) {
                $validator->errors()->add('tipo', 'El traslado solo está disponible con al menos dos sedes activas.');

                return;
            }

            $destino = $this->input('sede_destino_id');
            if (! is_string($destino) || $destino === '') {
                $validator->errors()->add('sede_destino_id', 'Indica la sede de destino.');

                return;
            }

            if ($destino === $this->input('sede_id')) {
                $validator->errors()->add('sede_destino_id', 'La sede de destino debe ser distinta a la de origen.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notas') && is_string($this->input('notas')) && trim($this->input('notas')) === '') {
            $this->merge(['notas' => null]);
        }

        if ($this->has('numero_lote') && is_string($this->input('numero_lote'))) {
            $lote = trim($this->input('numero_lote'));
            $this->merge(['numero_lote' => $lote === '' ? null : mb_substr($lote, 0, 128)]);
        }

        if ($this->has('fecha_vencimiento') && is_string($this->input('fecha_vencimiento')) && trim($this->input('fecha_vencimiento')) === '') {
            $this->merge(['fecha_vencimiento' => null]);
        }

        if ($this->has('sede_destino_id') && $this->input('sede_destino_id') === '') {
            $this->merge(['sede_destino_id' => null]);
        }
    }
}
