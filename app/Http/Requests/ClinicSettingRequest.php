<?php

namespace App\Http\Requests;

use App\Models\ClinicSetting;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Support\Caja\TicketAnchoMm;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validación de la actualización de la configuración general del tenant.
 *
 * El recurso es singleton (una fila por schema), por eso solo existe un
 * endpoint PUT/PATCH (`update`). No hay reglas de creación: la fila la
 * crea automáticamente {@see ClinicSetting::current()}.
 *
 * Alcance: SOLO datos del cliente. Las credenciales globales (Twilio,
 * Brevo) ya NO se piden aquí; viven en {@see PlatformSetting}
 * y se gestionan vía `PlatformSettingController` (solo superadmin).
 */
class ClinicSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Identidad fiscal
            'ruc' => ['nullable', 'string', 'size:11', 'regex:/^\d{11}$/'],
            'razon_social' => ['nullable', 'string', 'max:200'],
            'nombre_comercial' => ['nullable', 'string', 'max:150'],
            'direccion_fiscal' => ['nullable', 'string', 'max:255'],
            'distrito_id' => ['nullable', 'integer', 'exists:distritos,id'],

            // Branding
            //   - `logo` (archivo) y `clear_logo` (flag) viajan en el form
            //     multipart cuando el usuario adjunta o quita un logo desde
            //     el componente de subida. Validamos tipos comunes y limite
            //     razonable (2 MB) para evitar abusos.
            //   - `logo_path` NO viene del cliente: se persiste en el
            //     controller tras almacenar el archivo. Por eso no aparece
            //     en estas reglas.
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'clear_logo' => ['nullable', 'boolean'],
            'color_primario' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_secundario' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            // Contacto
            'email_institucional' => ['nullable', 'email', 'max:150'],
            'telefono_principal' => ['nullable', 'string', 'max:20'],
            'web_url' => ['nullable', 'url', 'max:200'],

            // Operación
            'duracion_cita_default_min' => ['required', 'integer', 'min:5', 'max:480'],
            'intervalo_agenda_min' => ['required', 'integer', 'min:5', 'max:120'],
            'agenda_hora_inicio' => ['required', 'date_format:H:i', 'regex:/^(?:[01]\d|2[0-3]):00$/'],
            'agenda_hora_fin' => ['required', 'date_format:H:i', 'regex:/^(?:[01]\d|2[0-3]):00$/'],
            'dias_anticipacion_cita' => ['required', 'integer', 'min:1', 'max:365'],
            'horas_min_cancelacion' => ['required', 'integer', 'min:0', 'max:168'],

            // Recordatorios
            'recordatorio_48h_activo' => ['required', 'boolean'],
            'recordatorio_2h_activo' => ['required', 'boolean'],
            'notificar_cita_whatsapp_activo' => ['required', 'boolean'],
            'notificar_grooming_creado_whatsapp_activo' => ['required', 'boolean'],
            'notificar_grooming_en_proceso_whatsapp_activo' => ['required', 'boolean'],
            'notificar_grooming_completado_whatsapp_activo' => ['required', 'boolean'],
            'notificar_grooming_cancelado_whatsapp_activo' => ['required', 'boolean'],
            'notificar_grooming_no_asistio_whatsapp_activo' => ['required', 'boolean'],
            'recordatorio_vacuna_activo' => ['required', 'boolean'],
            'recordatorio_vacuna_dias_antes' => ['required', 'integer', 'min:1', 'max:90'],
            'recordatorio_cumple_activo' => ['required', 'boolean'],

            // Facturación
            'moneda' => ['required', Rule::in(['PEN', 'USD'])],
            'igv_porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'precio_incluye_igv' => ['required', 'boolean'],
            'ticket_ancho_mm' => ['required', Rule::in(TicketAnchoMm::ALLOWED)],
            'emite_comprobantes_sunat' => ['required', 'boolean'],

            // APISUNAT (integración por tenant). Token en claro; el controller lo cifra.
            'apisunat_token' => ['nullable', 'string', 'max:8192'],
            'apisunat_mode' => ['nullable', Rule::in(['sandbox', 'produccion'])],
            'clear_apisunat' => ['nullable', 'boolean'],

            // "Remitente comercial visible". NO autentica nada (la
            // autenticación con Twilio/Brevo la hace el SaaS con sus
            // claves globales). Solo personaliza la firma de los
            // mensajes que envía la plataforma en nombre del cliente.
            'whatsapp_display_number' => ['nullable', 'string', 'max:30'],
            'email_from' => ['nullable', 'email', 'max:150'],
            'email_from_nombre' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ruc' => 'RUC',
            'razon_social' => 'razón social',
            'nombre_comercial' => 'nombre comercial',
            'direccion_fiscal' => 'dirección fiscal',
            'distrito_id' => 'distrito',
            'logo' => 'logo',
            'color_primario' => 'color primario',
            'color_secundario' => 'color secundario',
            'email_institucional' => 'correo institucional',
            'telefono_principal' => 'teléfono',
            'web_url' => 'sitio web',
            'duracion_cita_default_min' => 'duración de cita',
            'intervalo_agenda_min' => 'intervalo de agenda',
            'agenda_hora_inicio' => 'primera hora visible de la agenda',
            'agenda_hora_fin' => 'última hora visible de la agenda',
            'dias_anticipacion_cita' => 'días de anticipación',
            'horas_min_cancelacion' => 'horas mínimas para cancelar',
            'recordatorio_48h_activo' => 'recordatorio 48 h',
            'recordatorio_2h_activo' => 'recordatorio 2 h',
            'notificar_cita_whatsapp_activo' => 'notificación WhatsApp de citas',
            'notificar_grooming_creado_whatsapp_activo' => 'notificación al crear grooming',
            'notificar_grooming_en_proceso_whatsapp_activo' => 'notificación de grooming en proceso',
            'notificar_grooming_completado_whatsapp_activo' => 'notificación de grooming completado',
            'notificar_grooming_cancelado_whatsapp_activo' => 'notificación de grooming cancelado',
            'notificar_grooming_no_asistio_whatsapp_activo' => 'notificación de inasistencia a grooming',
            'recordatorio_vacuna_activo' => 'recordatorio de vacuna',
            'recordatorio_vacuna_dias_antes' => 'días previos al vencimiento',
            'recordatorio_cumple_activo' => 'recordatorio de cumpleaños',
            'moneda' => 'moneda',
            'igv_porcentaje' => 'porcentaje de IGV',
            'precio_incluye_igv' => 'precio incluye IGV',
            'ticket_ancho_mm' => 'ancho del ticket térmico',
            'emite_comprobantes_sunat' => 'emisión de comprobantes SUNAT',
            'apisunat_token' => 'token de APISUNAT',
            'apisunat_mode' => 'modo de APISUNAT',
            'whatsapp_display_number' => 'número visible de WhatsApp',
            'email_from' => 'correo de respuesta',
            'email_from_nombre' => 'nombre del remitente',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'recordatorio_48h_activo' => $this->boolean('recordatorio_48h_activo'),
            'recordatorio_2h_activo' => $this->boolean('recordatorio_2h_activo'),
            'notificar_cita_whatsapp_activo' => $this->boolean('notificar_cita_whatsapp_activo'),
            'notificar_grooming_creado_whatsapp_activo' => $this->boolean('notificar_grooming_creado_whatsapp_activo'),
            'notificar_grooming_en_proceso_whatsapp_activo' => $this->boolean('notificar_grooming_en_proceso_whatsapp_activo'),
            'notificar_grooming_completado_whatsapp_activo' => $this->boolean('notificar_grooming_completado_whatsapp_activo'),
            'notificar_grooming_cancelado_whatsapp_activo' => $this->boolean('notificar_grooming_cancelado_whatsapp_activo'),
            'notificar_grooming_no_asistio_whatsapp_activo' => $this->boolean('notificar_grooming_no_asistio_whatsapp_activo'),
            'recordatorio_vacuna_activo' => $this->boolean('recordatorio_vacuna_activo'),
            'recordatorio_cumple_activo' => $this->boolean('recordatorio_cumple_activo'),
            'precio_incluye_igv' => $this->boolean('precio_incluye_igv'),
            'emite_comprobantes_sunat' => $this->boolean('emite_comprobantes_sunat'),
            'clear_apisunat' => $this->boolean('clear_apisunat'),
            'clear_logo' => $this->boolean('clear_logo'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $start = (string) $this->input('agenda_hora_inicio', '');
            $end = (string) $this->input('agenda_hora_fin', '');

            if (
                preg_match('/^(?:[01]\d|2[0-3]):00$/', $start) === 1
                && preg_match('/^(?:[01]\d|2[0-3]):00$/', $end) === 1
                && $start >= $end
            ) {
                $v->errors()->add(
                    'agenda_hora_fin',
                    'La última hora visible debe ser posterior a la primera.',
                );
            }

            if (! $this->boolean('emite_comprobantes_sunat')) {
                return;
            }

            $tenantId = $this->user()?->tenant_id;
            if ($tenantId === null) {
                $v->errors()->add('emite_comprobantes_sunat', __('config_clinic.validation.emite_sin_tenant'));

                return;
            }

            $tenant = Tenant::query()->find($tenantId);
            if (! PlanCapabilities::facturaElectronica($tenant)) {
                $v->errors()->add('emite_comprobantes_sunat', __('config_clinic.validation.emite_sin_plan'));
            }
        });
    }
}
