<?php

namespace App\Http\Requests;

use App\Grooming\GroomingCatalogoServicio;
use App\Http\Requests\Concerns\AssignsAuthenticatedResponsable;
use App\Support\Grooming\GroomingTurnoServicioRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroomingTurnoRequest extends FormRequest
{
    use AssignsAuthenticatedResponsable;

    public function authorize(): bool
    {
        return $this->user()?->can('grooming.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $out = [];
        foreach (['responsable_id', 'sede_id'] as $key) {
            $v = $this->input($key);
            if ($v === '' || $v === null) {
                $out[$key] = null;
            }
        }
        $n = $this->input('notas');
        if (is_string($n) && trim($n) === '') {
            $out['notas'] = null;
        }
        $s = $this->input('servicio');
        if (is_string($s)) {
            $out['servicio'] = trim($s);
        }
        $sd = $this->input('servicio_detalle');
        if (is_string($sd)) {
            $trim = trim($sd);
            $out['servicio_detalle'] = $trim === '' ? null : $trim;
        }

        $svc = $out['servicio'] ?? (is_string($s) ? trim($s) : null);
        if ($svc !== GroomingCatalogoServicio::OTRO_PERSONALIZADO) {
            $out['servicio_detalle'] = null;
        }
        if ($out !== []) {
            $this->merge($out);
        }

        $this->stripResponsableFromUpdate();
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
            'sede_id' => [
                'nullable',
                'uuid',
                Rule::exists('sedes', 'id')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId)->where('activa', true),
                ),
            ],
            'inicio_at' => ['required', 'date'],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            ...GroomingTurnoServicioRules::servicioFields(),
            'notas' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
