<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AssignsAuthenticatedVeterinario;
use App\Models\Consulta;
use App\Models\Receta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecetaRequest extends FormRequest
{
    use AssignsAuthenticatedVeterinario;

    public function authorize(): bool
    {
        return $this->user()?->can('recetas.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $out = [];
        $cid = $this->input('consulta_id');
        if ($cid === '' || $cid === null) {
            $out['consulta_id'] = null;
        }
        foreach (['veterinario_id', 'sede_id'] as $key) {
            $v = $this->input($key);
            if ($v === '' || $v === null) {
                $out[$key] = null;
            }
        }
        $obs = $this->input('observaciones');
        if (is_string($obs) && trim($obs) === '') {
            $out['observaciones'] = null;
        }
        $lineas = $this->input('lineas');
        if (is_array($lineas)) {
            $clean = [];
            foreach ($lineas as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $r = $row;
                if (($r['producto_id'] ?? '') === '') {
                    $r['producto_id'] = null;
                }
                foreach (['posologia', 'instrucciones'] as $k) {
                    if (isset($r[$k]) && is_string($r[$k]) && trim($r[$k]) === '') {
                        $r[$k] = null;
                    }
                }
                $dd = $r['duracion_dias'] ?? null;
                if ($dd === '' || $dd === null) {
                    $r['duracion_dias'] = null;
                }
                $clean[$i] = $r;
            }
            $out['lineas'] = $clean;
        }
        if ($out !== []) {
            $this->merge($out);
        }

        $this->stripVeterinarioFromUpdate();
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $cid = $this->input('consulta_id');
            if ($cid === null || $cid === '') {
                return;
            }
            $pid = $this->input('paciente_id');
            $consulta = Consulta::query()->with('historiaClinica:id,paciente_id')->find($cid);
            if ($consulta === null || $consulta->historiaClinica === null
                || (string) $consulta->historiaClinica->paciente_id !== (string) $pid) {
                $v->errors()->add('consulta_id', __('recetas.validation.consulta_invalida'));

                return;
            }
        });
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
            'consulta_id' => ['nullable', 'uuid', 'exists:consultas,id'],
            'sede_id' => [
                'nullable',
                'uuid',
                Rule::exists('sedes', 'id')->where(
                    fn ($q) => $q->where('tenant_id', $tenantId)->where('activa', true),
                ),
            ],
            'emitida_at' => ['required', 'date'],
            'estado' => ['required', 'string', Rule::in(Receta::ESTADOS)],
            'observaciones' => ['nullable', 'string', 'max:20000'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => [
                'nullable',
                'uuid',
                Rule::exists('productos', 'id')->where(
                    fn ($q) => $q->where('medicamento', true)->where('activo', true),
                ),
            ],
            'lineas.*.nombre_medicamento' => ['required', 'string', 'max:500'],
            'lineas.*.posologia' => ['nullable', 'string', 'max:2000'],
            'lineas.*.duracion_dias' => ['nullable', 'integer', 'min:1', 'max:999'],
            'lineas.*.instrucciones' => ['nullable', 'string', 'max:2000'],
            'lineas.*.orden' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ];
    }
}
