<?php

namespace App\Http\Requests;

use App\Hotel\HotelCatalogoMode;
use App\Hotel\HotelCatalogoTipoEstancia;
use App\Support\Hotel\HotelEstanciaTipoRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHotelEstanciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hotel.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $out = [];
        foreach (['responsable_id', 'sede_id', 'egreso_at'] as $key) {
            $v = $this->input($key);
            if ($v === '' || $v === null) {
                $out[$key] = null;
            }
        }
        $n = $this->input('notas');
        if (is_string($n) && trim($n) === '') {
            $out['notas'] = null;
        }

        if (! HotelCatalogoMode::usaCatalogoPersonalizado()) {
            $t = $this->input('tipo_estancia');
            if (is_string($t)) {
                $out['tipo_estancia'] = trim($t);
            }
            $td = $this->input('tipo_detalle');
            if (is_string($td)) {
                $trim = trim($td);
                $out['tipo_detalle'] = $trim === '' ? null : $trim;
            }

            $tipo = $out['tipo_estancia'] ?? (is_string($t) ? trim($t) : null);
            if ($tipo !== HotelCatalogoTipoEstancia::OTRO_PERSONALIZADO) {
                $out['tipo_detalle'] = null;
            }
        }

        if ($out !== []) {
            $this->merge($out);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = tenant_id();

        return [
            'paciente_id' => [
                'required',
                'uuid',
                Rule::exists('pacientes', 'id')->where(
                    fn ($q) => $q->where('activo', true),
                ),
            ],
            'responsable_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId),
                ),
            ],
            'sede_id' => [
                'nullable',
                'uuid',
                Rule::exists('sedes', 'id')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId)->where('activa', true),
                ),
            ],
            'ingreso_at' => ['required', 'date'],
            'egreso_at' => ['nullable', 'date', 'after_or_equal:ingreso_at'],
            ...HotelEstanciaTipoRules::tipoFields(),
            'notas' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
