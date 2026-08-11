<?php

namespace App\Http\Requests;

use App\Models\ConsultaCargoLinea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertConsultaCargoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return $u !== null
            && (
                $u->can('consulta-cargos.manage')
                || $u->can('historias-clinicas.update')
                || $u->can('hospitalizacion.update')
                || $u->can('grooming.update')
                || $u->can('hotel.update')
                || $u->can('vacunaciones.update')
            );
    }

    protected function prepareForValidation(): void
    {
        $lineas = $this->input('lineas');
        if (! is_array($lineas)) {
            return;
        }

        $filtered = array_values(array_filter(
            $lineas,
            static fn ($row): bool => is_array($row) && trim((string) ($row['concepto'] ?? '')) !== '',
        ));

        foreach ($filtered as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (['cantidad', 'precio_unitario'] as $key) {
                if (isset($row[$key]) && is_numeric($row[$key])) {
                    $filtered[$i][$key] = round((float) $row[$key], 2);
                }
            }
            if (isset($row['descuento_importe']) && is_numeric($row['descuento_importe'])) {
                $filtered[$i]['descuento_importe'] = round((float) $row['descuento_importe'], 2);
            }

            $productoId = $row['producto_id'] ?? null;
            if ($productoId === '' || $productoId === null) {
                $filtered[$i]['producto_id'] = null;
            }
        }

        $this->merge(['lineas' => $filtered]);
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $lineas = $this->input('lineas');
            if (! is_array($lineas)) {
                return;
            }

            foreach ($lineas as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (($row['tipo_linea'] ?? '') !== ConsultaCargoLinea::TIPO_PRODUCTO) {
                    continue;
                }
                $pid = $row['producto_id'] ?? null;
                if ($pid === null || $pid === '') {
                    $v->errors()->add(
                        "lineas.{$i}.producto_id",
                        __('consulta-cargos.validation.producto_requerido'),
                    );
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notas' => ['nullable', 'string', 'max:20000'],
            'lineas' => ['nullable', 'array', 'max:200'],
            'lineas.*.tipo_linea' => [
                'required',
                'string',
                Rule::in([
                    ConsultaCargoLinea::TIPO_SERVICIO,
                    ConsultaCargoLinea::TIPO_PRODUCTO,
                    ConsultaCargoLinea::TIPO_OTRO,
                ]),
            ],
            'lineas.*.concepto' => ['required', 'string', 'max:500'],
            'lineas.*.cantidad' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lineas.*.descuento_importe' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lineas.*.producto_id' => [
                'nullable',
                'uuid',
                Rule::exists('productos', 'id')->where(
                    fn ($query) => $query->where('activo', true),
                ),
            ],
        ];
    }
}
