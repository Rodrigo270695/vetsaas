<?php

namespace App\Http\Requests;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para crear/editar suscripciones desde el panel del superadmin.
 *
 * Notas:
 *   - En operación normal las suscripciones nacen desde el checkout de
 *     Orvae cuando el cliente paga. Este endpoint manual existe para
 *     soporte, migración y casos especiales (ej: cliente VIP que paga
 *     fuera de Orvae con transferencia).
 *   - Un tenant solo puede tener UNA suscripción NO cancelled a la vez
 *     (existe un UNIQUE INDEX condicional en BD). Si ya tiene una activa
 *     y se intenta crear otra, falla con 23505 — preferimos detectarlo acá.
 */
class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Subscription|null $subscription */
        $subscription = $this->route('suscripcion');

        return [
            'tenant_id' => [
                'required',
                'uuid',
                Rule::exists('tenants', 'id')->whereNull('deleted_at'),
                // Defensa adicional: si vamos a CREAR, validamos que el
                // tenant no tenga ya una suscripción activa. En UPDATE
                // esto no aplica porque la propia suscripción seguramente
                // es la "activa" del tenant.
                function ($attribute, $value, $fail) use ($subscription) {
                    if ($subscription !== null) {
                        return;
                    }
                    $existing = Subscription::query()
                        ->where('tenant_id', $value)
                        ->where('estado', '!=', 'cancelled')
                        ->exists();
                    if ($existing) {
                        $fail('Este tenant ya tiene una suscripción activa. Cancela la anterior antes de crear una nueva.');
                    }
                },
            ],
            'plan_id' => [
                'required',
                'uuid',
                Rule::exists('plans', 'id'),
            ],
            'estado' => [
                'required',
                'string',
                Rule::in(['trial', 'active', 'grace', 'suspended', 'cancelled']),
            ],
            'ciclo' => [
                'required',
                'string',
                Rule::in(['mensual', 'anual']),
            ],
            'precio_pactado' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'descuento_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'trial_ends_at' => ['nullable', 'date'],
            'current_period_start' => ['nullable', 'date'],
            'current_period_end' => ['nullable', 'date', 'after_or_equal:current_period_start'],
            'grace_ends_at' => ['nullable', 'date'],
            'proximo_cobro_at' => ['nullable', 'date'],
            'cancel_reason' => ['nullable', 'string', 'max:500'],
            'cancel_feedback' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tenant_id' => 'tenant',
            'plan_id' => 'plan',
            'estado' => 'estado',
            'ciclo' => 'ciclo de facturación',
            'precio_pactado' => 'precio pactado',
            'descuento_pct' => 'descuento (%)',
            'trial_ends_at' => 'fin del periodo de prueba',
            'current_period_start' => 'inicio del periodo',
            'current_period_end' => 'fin del periodo',
            'grace_ends_at' => 'fin del periodo de gracia',
            'proximo_cobro_at' => 'próximo cobro',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'descuento_pct' => filled($this->input('descuento_pct'))
                ? $this->input('descuento_pct')
                : 0,
            'cancel_reason' => filled($this->input('cancel_reason'))
                ? trim((string) $this->input('cancel_reason'))
                : null,
            'cancel_feedback' => filled($this->input('cancel_feedback'))
                ? trim((string) $this->input('cancel_feedback'))
                : null,
        ]);
    }
}
