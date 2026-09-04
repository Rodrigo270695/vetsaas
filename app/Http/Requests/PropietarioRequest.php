<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPlanIntLimits;
use App\Models\Propietario;
use App\Rules\ExistsDistritoId;
use App\Support\PropietarioTipoDocumento;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropietarioRequest extends FormRequest
{
    use ValidatesPlanIntLimits;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['nullable', 'string', 'max:20', Rule::in(PropietarioTipoDocumento::VALUES)],
            'numero_documento' => ['nullable', 'string', 'max:20'],
            'nombres' => ['required', 'string', 'max:150'],
            'apellidos' => ['nullable', 'string', 'max:150'],
            'razon_social' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'telefono_alt' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'distrito_id' => ['nullable', 'integer', new ExistsDistritoId],
            'notas' => ['nullable', 'string', 'max:5000'],
            'activo' => ['required', 'boolean'],
            'mascotas' => ['sometimes', 'array', 'max:15'],
            'mascotas.*.nombre' => ['nullable', 'string', 'max:120'],
            'mascotas.*.especie' => ['nullable', 'string', 'max:80'],
            'mascotas.*.raza' => ['nullable', 'string', 'max:120'],
            'mascotas.*.sexo' => ['nullable', 'string', 'size:1', Rule::in(['M', 'H', 'U'])],
            'mascotas.*.fecha_nacimiento' => ['nullable', 'date'],
            'mascotas.*.peso_kg' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'mascotas.*.color' => ['nullable', 'string', 'max:80'],
            'mascotas.*.esterilizado' => ['nullable'],
            'mascotas.*.notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $tipo = $this->input('tipo_documento');
        if ($tipo === null || $tipo === '') {
            $tipoNormalizado = null;
        } elseif (is_string($tipo)) {
            $tipoNormalizado = strtoupper(trim($tipo));
            $tipoNormalizado = $tipoNormalizado === '' ? null : $tipoNormalizado;
        } else {
            $tipoNormalizado = null;
        }

        $numero = $this->input('numero_documento');
        if (is_string($numero)) {
            $numero = trim($numero);
            if ($tipoNormalizado === 'DNI' || $tipoNormalizado === 'RUC') {
                $numero = preg_replace('/\D+/', '', $numero) ?? '';
            }
            $numero = $numero === '' ? null : $numero;
        } else {
            $numero = null;
        }

        if (is_array($this->input('mascotas'))) {
            $normalized = [];
            foreach ($this->input('mascotas') as $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach (['nombre', 'especie', 'raza', 'sexo', 'fecha_nacimiento', 'peso_kg', 'color', 'notas'] as $key) {
                    if (array_key_exists($key, $row) && is_string($row[$key]) && trim($row[$key]) === '') {
                        $row[$key] = null;
                    }
                }
                $normalized[] = $row;
            }
            $this->merge(['mascotas' => $normalized]);
        }

        $this->merge([
            'activo' => $this->boolean('activo'),
            'tipo_documento' => $tipoNormalizado,
            'numero_documento' => $numero,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $this->enforcePlanIntLimitsOnCreate($validator, ['max_propietarios']);

        $namedPets = 0;
        $mascotas = $this->input('mascotas');
        if (is_array($mascotas)) {
            foreach ($mascotas as $row) {
                if (is_array($row) && trim((string) ($row['nombre'] ?? '')) !== '') {
                    $namedPets++;
                }
            }
        }
        if ($namedPets > 0) {
            $this->enforcePlanIntLimitsOnCreate($validator, ['max_pacientes'], $namedPets);
        }

        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $numero = $this->input('numero_documento');
            if (! is_string($numero) || $numero === '') {
                return;
            }

            $tipo = $this->input('tipo_documento');
            $tipoKey = is_string($tipo) ? strtoupper($tipo) : '';

            $propietario = $this->route('propietario');
            $ignoreId = $propietario instanceof Propietario ? $propietario->id : $propietario;

            $exists = Propietario::query()
                ->whereRaw('COALESCE(UPPER(tipo_documento), \'\') = ?', [$tipoKey])
                ->where('numero_documento', $numero)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists();

            if ($exists) {
                $v->errors()->add('numero_documento', __('propietarios.validation.documento_duplicado'));
            }
        });
    }
}
