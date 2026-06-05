<?php

namespace App\Http\Requests;

use App\Models\Consulta;
use App\Models\PedidoLaboratorio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePedidoLaboratorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('laboratorio.create') ?? false;
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
        $dest = $this->input('laboratorio_destino');
        if (is_string($dest) && trim($dest) === '') {
            $out['laboratorio_destino'] = null;
        }
        $lineas = $this->input('lineas');
        if (is_array($lineas)) {
            $clean = [];
            foreach ($lineas as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $r = $row;
                foreach (['indicaciones', 'resultado'] as $k) {
                    if (isset($r[$k]) && is_string($r[$k]) && trim($r[$k]) === '') {
                        $r[$k] = null;
                    }
                }
                $rat = $r['resultado_at'] ?? null;
                if ($rat === '' || $rat === null) {
                    $r['resultado_at'] = null;
                }
                $r['clear_resultado_archivo'] = filter_var(
                    $r['clear_resultado_archivo'] ?? false,
                    FILTER_VALIDATE_BOOLEAN,
                );
                $clean[$i] = $r;
            }
            $out['lineas'] = $clean;
        }
        if ($out !== []) {
            $this->merge($out);
        }
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
                $v->errors()->add('consulta_id', __('laboratorio.validation.consulta_invalida'));

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
            'veterinario_id' => [
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
            'solicitado_at' => ['required', 'date'],
            'estado' => ['sometimes', 'string', Rule::in(PedidoLaboratorio::ESTADOS_CREACION)],
            'laboratorio_destino' => ['nullable', 'string', 'max:200'],
            'observaciones' => ['nullable', 'string', 'max:20000'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.nombre_examen' => ['required', 'string', 'max:500'],
            'lineas.*.indicaciones' => ['nullable', 'string', 'max:2000'],
            'lineas.*.resultado' => ['nullable', 'string', 'max:20000'],
            'lineas.*.resultado_at' => ['nullable', 'date'],
            'lineas.*.resultado_archivo' => ['nullable', 'file', 'mimes:pdf,jpeg,jpg,png,webp', 'max:12288'],
            'lineas.*.clear_resultado_archivo' => ['nullable', 'boolean'],
            'lineas.*.orden' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ];
    }
}
