<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlatformSettingRequest;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración global de la plataforma (Plataforma → Configuración).
 *
 * Edita la única fila de `public.platform_settings`, donde viven las
 * credenciales de los proveedores externos compartidos por TODAS las
 * clínicas (Twilio para WhatsApp, Brevo para correo transaccional).
 *
 * Acceso:
 *   - Las rutas (`/plataforma/configuracion`) son operadas desde el
 *     host central (no requieren contexto de tenant) y están protegidas
 *     por `permission:platform-settings.view` / `.update`. Solo el
 *     `superadmin` tiene esos permisos.
 *   - A diferencia de {@see ClinicSettingController}, este controller
 *     NO usa `tenant.required`: la configuración es global, no por
 *     tenant.
 *
 * Credenciales sensibles:
 *   - Se cifran con `Crypt::encryptString` (AES-256-CBC derivada de
 *     APP_KEY) antes de persistirse.
 *   - NUNCA viajan al frontend en claro: solo se exponen los flags
 *     `*_configurado`. Para limpiar una integración existente se envía
 *     `clear_<servicio>=true`.
 */
class PlatformSettingController extends Controller
{
    public function show(): Response
    {
        $setting = PlatformSetting::current()->load('actualizadoPor:id,name,email');

        return Inertia::render('plataforma/configuracion/index', [
            'setting' => $this->presentSetting($setting),
        ]);
    }

    public function update(PlatformSettingRequest $request): RedirectResponse
    {
        $setting = PlatformSetting::current();
        $data = $request->validated();

        // Campos planos
        $setting->fill([
            'twilio_default_from' => $data['twilio_default_from'] ?? null,
            'brevo_default_from_email' => $data['brevo_default_from_email'] ?? null,
            'brevo_default_from_name' => $data['brevo_default_from_name'] ?? null,
            'updated_by_id' => Auth::id(),
        ]);

        $this->applyIntegrationSecrets($setting, $data);

        $setting->save();

        return back()->with('success', 'Configuración global actualizada correctamente.');
    }

    /**
     * Aplica los cambios de credenciales sensibles a las integraciones
     * globales (Twilio / Brevo). Patrón "tres caminos":
     *
     *   1) Si `clear_<servicio>=true`, se limpian todas las credenciales
     *      y se baja el flag `<servicio>_configurado` a false.
     *   2) Si llegaron credenciales nuevas (no vacías), se cifran y se
     *      levanta `<servicio>_configurado` cuando hay credenciales
     *      completas (todas las piezas presentes tras este request).
     *   3) Si no llegó ni una cosa ni la otra, NO se toca el campo.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyIntegrationSecrets(PlatformSetting $setting, array $data): void
    {
        // ── Twilio (necesita SID + Token completos para considerarse configurado) ──
        if (($data['clear_twilio'] ?? false) === true) {
            $setting->twilio_sid_enc = null;
            $setting->twilio_token_enc = null;
            $setting->twilio_configurado = false;
        } else {
            if (! empty($data['twilio_sid'])) {
                $setting->twilio_sid_enc = Crypt::encryptString($data['twilio_sid']);
            }

            if (! empty($data['twilio_token'])) {
                $setting->twilio_token_enc = Crypt::encryptString($data['twilio_token']);
            }

            // Solo marcamos configurado si tenemos ambos lados de las
            // credenciales tras este request (preexistentes + nuevos).
            $setting->twilio_configurado = ($setting->twilio_sid_enc !== null)
                && ($setting->twilio_token_enc !== null);
        }

        // ── Brevo ──
        if (($data['clear_brevo'] ?? false) === true) {
            $setting->brevo_api_key_enc = null;
            $setting->brevo_configurado = false;
        } else {
            if (! empty($data['brevo_api_key'])) {
                $setting->brevo_api_key_enc = Crypt::encryptString($data['brevo_api_key']);
            }

            $setting->brevo_configurado = $setting->brevo_api_key_enc !== null;
        }
    }

    /**
     * Construye el payload que se expone al frontend.
     *
     * Las credenciales jamás se devuelven en claro: solo flags booleanos
     * `*_configurado` para reflejar el estado de la integración.
     *
     * @return array<string, mixed>
     */
    private function presentSetting(PlatformSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'twilio_default_from' => $setting->twilio_default_from,
            'twilio_configurado' => $setting->twilio_configurado,
            'brevo_default_from_email' => $setting->brevo_default_from_email,
            'brevo_default_from_name' => $setting->brevo_default_from_name,
            'brevo_configurado' => $setting->brevo_configurado,
            'updated_at' => $setting->updated_at?->toIso8601String(),
            'actualizado_por' => $setting->actualizadoPor ? [
                'id' => $setting->actualizadoPor->id,
                'name' => $setting->actualizadoPor->name,
                'email' => $setting->actualizadoPor->email,
            ] : null,
        ];
    }
}
