<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertConsultaPlanTratamientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('historias-clinicas-planes.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $lineas = $this->input('lineas');
        if (! is_array($lineas)) {
            return;
        }

        $filtered = array_values(array_filter(
            $lineas,
            static fn ($row): bool => is_array($row) && trim((string) ($row['medicamento'] ?? '')) !== '',
        ));

        $this->merge(['lineas' => $filtered]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'indicaciones' => ['nullable', 'string', 'max:20000'],
            'estado' => ['nullable', 'string', Rule::in(['activo', 'completado', 'suspendido'])],
            'lineas' => ['nullable', 'array', 'max:100'],
            'lineas.*.medicamento' => ['required', 'string', 'max:500'],
            'lineas.*.producto_id' => [
                'nullable',
                'uuid',
                Rule::exists('productos', 'id')->where(
                    fn ($query) => $query->where('medicamento', true)->where('activo', true),
                ),
            ],
            'lineas.*.cantidad' => ['nullable', 'numeric', 'min:0.001', 'max:999999'],
            'lineas.*.dosis' => ['nullable', 'string', 'max:255'],
            'lineas.*.unidad' => ['nullable', 'string', 'max:64'],
            'lineas.*.via' => ['nullable', 'string', 'max:128'],
            'lineas.*.frecuencia' => ['nullable', 'string', 'max:255'],
            'lineas.*.lote' => ['nullable', 'string', 'max:128'],
            'lineas.*.notas' => ['nullable', 'string', 'max:20000'],
            'lineas.*.anadido_en' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $lineas = $this->input('lineas', []);
            if (! is_array($lineas)) {
                return;
            }

            foreach (array_values($lineas) as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $productoId = trim((string) ($row['producto_id'] ?? ''));
                if ($productoId === '') {
                    continue;
                }

                $cantidad = $row['cantidad'] ?? null;
                if ($cantidad === null || $cantidad === '' || ! is_numeric($cantidad) || (float) $cantidad <= 0) {
                    $validator->errors()->add(
                        "lineas.{$index}.cantidad",
                        __('historias-clinicas.plan.stock.cantidad_requerida'),
                    );
                }
            }
        });
    }
}
