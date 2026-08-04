<?php

namespace App\Http\Requests;

use App\Models\VentaPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroomingAdelantoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can('ventas.create')
            && $user->can('grooming.view');
    }

    protected function prepareForValidation(): void
    {
        $out = [];
        $n = $this->input('notas');
        if (is_string($n) && trim($n) === '') {
            $out['notas'] = null;
        }
        $mr = $this->input('monto_recibido');
        if ($mr === '' || $mr === null) {
            $out['monto_recibido'] = null;
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
        return [
            'caja_sesion_id' => ['nullable', 'uuid', 'exists:caja_sesiones,id'],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'metodo_pago' => ['required', 'string', Rule::in(VentaPago::METODOS)],
            'monto_recibido' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
