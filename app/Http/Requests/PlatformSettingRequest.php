<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de la actualización de la configuración global del SaaS.
 *
 * El recurso es singleton (una sola fila en `public.platform_settings`),
 * por eso solo existe un endpoint PUT/PATCH (`update`). No hay reglas de
 * creación: la fila la crea automáticamente
 * {@see \App\Models\PlatformSetting::current()}.
 *
 * Las credenciales sensibles viajan opcionalmente: si el cliente no las
 * manda, el controller deja el valor previo intacto. Para limpiarlas se
 * usa el flag `clear_<servicio>=true`.
 */
class PlatformSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Twilio (WhatsApp Cloud) ──
            'twilio_sid' => ['nullable', 'string', 'max:255'],
            'twilio_token' => ['nullable', 'string', 'max:255'],
            // Formato E.164 (ej: `+14155238886`).
            'twilio_default_from' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9]{8,15}$/'],
            'clear_twilio' => ['nullable', 'boolean'],

            // ── Brevo (correo transaccional) ──
            'brevo_api_key' => ['nullable', 'string', 'max:255'],
            'brevo_default_from_email' => ['nullable', 'email', 'max:150'],
            'brevo_default_from_name' => ['nullable', 'string', 'max:100'],
            'clear_brevo' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'twilio_sid' => 'Twilio Account SID',
            'twilio_token' => 'Twilio Auth Token',
            'twilio_default_from' => 'número WhatsApp por defecto',
            'brevo_api_key' => 'API key de Brevo',
            'brevo_default_from_email' => 'correo remitente por defecto',
            'brevo_default_from_name' => 'nombre del remitente por defecto',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'clear_twilio' => $this->boolean('clear_twilio'),
            'clear_brevo' => $this->boolean('clear_brevo'),
        ]);
    }
}
