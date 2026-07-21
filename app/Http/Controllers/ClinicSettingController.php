<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureTenant;
use App\Http\Requests\ClinicSettingRequest;
use App\Models\ClinicSetting;
use App\Models\Departamento;
use App\Services\Tenancy\TenantShowcaseService;
use App\Support\Caja\TicketAnchoMm;
use App\Support\PlanCapabilities;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración general de la clínica (Configuración → General).
 *
 * Edita la única fila de `cfg_clinic_settings` que existe dentro del
 * schema del tenant activo. La fila se autoprovisiona la primera vez
 * que un usuario abre la pantalla (via {@see ClinicSetting::current()}).
 *
 * Acceso:
 *   - El módulo solo tiene sentido en el contexto de un tenant. Las
 *     rutas (`/configuracion/general`) llevan el alias de middleware
 *     `tenant.required` (ver {@see EnsureTenant}),
 *     que garantiza que al entrar al método hay un tenant resuelto.
 *     Si llega un request al host central, el middleware:
 *       · responde 404 para roles operativos (defensa en profundidad);
 *       · renderiza `shared/tenant-required` para superadmin (UX).
 *   - `tenant.match-user` (aplicado más arriba) impide cross-tenant
 *     access entre clínicas distintas.
 *
 * Alcance: SOLO datos del cliente. Las credenciales globales del SaaS
 * (Twilio, Brevo) se gestionan vía {@see PlatformSettingController}.
 *
 * Logo:
 *   - Se sube como `multipart/form-data` (campo `logo`) y se almacena
 *     en el disco `public` bajo `tenants/<slug>/logos/<uuid>.<ext>`.
 *     La URL pública se genera en el accesor `logo_url` del modelo.
 *   - Para "borrar" el logo actual se envía `clear_logo=true`.
 *
 * Credenciales sensibles (Nubefact):
 *   - El token se cifra con `Crypt::encryptString` antes de persistirse.
 *   - NUNCA viaja al frontend en claro: solo el flag `nubefact_configurado`.
 *   - Para limpiarlas se envía `clear_nubefact=true`.
 */
class ClinicSettingController extends Controller
{
    public function show(TenantManager $tenants): Response
    {
        $setting = ClinicSetting::current()
            ->load('actualizadoPor:id,name,email', 'distritoModel.provincia.departamento');

        $tenant = $tenants->current();
        $tenantModel = $tenant?->tenant;
        $planPermiteFacturaElectronica = PlanCapabilities::facturaElectronica($tenantModel);

        $departamentos = Departamento::query()
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('configuracion/general/index', [
            'setting' => $this->presentSetting($setting, $planPermiteFacturaElectronica),
            'clinic_header' => $tenant === null ? null : [
                'id' => $tenant->id(),
                'slug' => $tenant->slug,
                'razon_social' => $tenant->tenant->razon_social ?? null,
                'nombre_comercial' => $tenant->tenant->nombre_comercial ?? null,
            ],
            'departamentos' => $departamentos,
            'plan_permite_factura_electronica' => $planPermiteFacturaElectronica,
        ]);
    }

    public function update(ClinicSettingRequest $request, TenantManager $tenants): RedirectResponse
    {
        $setting = ClinicSetting::current();
        $data = $request->validated();

        $tenantModel = $tenants->current()?->tenant;
        $planPermiteFacturaElectronica = PlanCapabilities::facturaElectronica($tenantModel);
        if (! $planPermiteFacturaElectronica) {
            $data['emite_comprobantes_sunat'] = false;
        }

        $horarioAtencion = is_array($setting->horario_atencion)
            ? $setting->horario_atencion
            : [];
        $horarioAtencion['agenda_hora_inicio'] = $data['agenda_hora_inicio'];
        $horarioAtencion['agenda_hora_fin'] = $data['agenda_hora_fin'];

        // Campos planos: simple mapping.
        $setting->fill([
            'ruc' => $data['ruc'] ?? null,
            'razon_social' => $data['razon_social'] ?? null,
            'nombre_comercial' => $data['nombre_comercial'] ?? null,
            'direccion_fiscal' => $data['direccion_fiscal'] ?? null,
            'distrito_id' => $data['distrito_id'] ?? null,
            'color_primario' => $data['color_primario'] ?? null,
            'color_secundario' => $data['color_secundario'] ?? null,
            'email_institucional' => $data['email_institucional'] ?? null,
            'telefono_principal' => $data['telefono_principal'] ?? null,
            'web_url' => $data['web_url'] ?? null,
            'duracion_cita_default_min' => $data['duracion_cita_default_min'],
            'intervalo_agenda_min' => $data['intervalo_agenda_min'],
            'horario_atencion' => $horarioAtencion,
            'dias_anticipacion_cita' => $data['dias_anticipacion_cita'],
            'horas_min_cancelacion' => $data['horas_min_cancelacion'],
            'recordatorio_48h_activo' => $data['recordatorio_48h_activo'],
            'recordatorio_2h_activo' => $data['recordatorio_2h_activo'],
            'notificar_cita_whatsapp_activo' => $data['notificar_cita_whatsapp_activo'],
            'notificar_grooming_creado_whatsapp_activo' => $data['notificar_grooming_creado_whatsapp_activo'],
            'notificar_grooming_en_proceso_whatsapp_activo' => $data['notificar_grooming_en_proceso_whatsapp_activo'],
            'notificar_grooming_completado_whatsapp_activo' => $data['notificar_grooming_completado_whatsapp_activo'],
            'notificar_grooming_cancelado_whatsapp_activo' => $data['notificar_grooming_cancelado_whatsapp_activo'],
            'notificar_grooming_no_asistio_whatsapp_activo' => $data['notificar_grooming_no_asistio_whatsapp_activo'],
            'recordatorio_vacuna_activo' => $data['recordatorio_vacuna_activo'],
            'recordatorio_vacuna_dias_antes' => $data['recordatorio_vacuna_dias_antes'],
            'recordatorio_cumple_activo' => $data['recordatorio_cumple_activo'],
            'moneda' => $data['moneda'],
            'igv_porcentaje' => $data['igv_porcentaje'],
            'precio_incluye_igv' => $data['precio_incluye_igv'],
            'ticket_ancho_mm' => $data['ticket_ancho_mm'],
            'emite_comprobantes_sunat' => $data['emite_comprobantes_sunat'],
            // Remitente comercial visible (NO autentica)
            'email_from' => $data['email_from'] ?? null,
            'email_from_nombre' => $data['email_from_nombre'] ?? null,
            'whatsapp_display_number' => $data['whatsapp_display_number'] ?? null,
            'updated_by_id' => Auth::id(),
        ]);

        $this->applyLogo($setting, $request, $tenants);

        if ($planPermiteFacturaElectronica) {
            $this->applyApisunatToken($setting, $data);
        }

        $setting->save();

        app(TenantShowcaseService::class)->forgetCache();

        return back()->with(['success' => 'Configuración actualizada correctamente.']);
    }

    /**
     * Aplica los cambios al logo de la clínica.
     *
     *   1) Si `clear_logo=true`, borra el archivo físico y limpia el
     *      campo `logo_path` (independientemente de si llegó un archivo).
     *   2) Si llegó un archivo nuevo en `logo`, lo guarda en el disco
     *      `public` bajo `tenants/<slug>/logos/<uuid>.<ext>` y borra el
     *      logo anterior (si existía) para no dejar huérfanos.
     *   3) Si no llegó ni una cosa ni la otra, NO se toca el campo.
     */
    private function applyLogo(
        ClinicSetting $setting,
        ClinicSettingRequest $request,
        TenantManager $tenants,
    ): void {
        $disk = Storage::disk('public');

        if (($request->validated('clear_logo') ?? false) === true) {
            if ($setting->logo_path && $disk->exists($setting->logo_path)) {
                $disk->delete($setting->logo_path);
            }
            $setting->logo_path = null;

            return;
        }

        if (! $request->hasFile('logo')) {
            return;
        }

        $tenant = $tenants->current();
        // Slug del tenant para namespaciar archivos en disco. Si por
        // alguna razón no hay tenant (no debería ocurrir gracias al
        // middleware `tenant.required`), caemos a "shared" para que
        // el archivo no quede en la raíz del disco.
        $slug = $tenant?->slug ?? 'shared';

        $previous = $setting->logo_path;

        $file = $request->file('logo');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = "tenants/{$slug}/logos/{$filename}";

        // putFileAs garantiza que el archivo se mueve a su destino final
        // dentro del disco `public`; el contenido queda accesible vía
        // `/storage/...` gracias a `php artisan storage:link`.
        $disk->putFileAs(
            "tenants/{$slug}/logos",
            $file,
            $filename,
            'public',
        );

        $setting->logo_path = $path;

        if ($previous && $previous !== $path && $disk->exists($previous)) {
            $disk->delete($previous);
        }
    }

    /**
     * Aplica los cambios al token APISUNAT del tenant. Tres caminos:
     *
     *   1) Si `clear_apisunat=true`, limpia token y baja el flag.
     *   2) Si llegó `apisunat_token`, se cifra y se levanta el flag.
     *   3) Si no llegó nada, se preserva lo existente.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyApisunatToken(ClinicSetting $setting, array $data): void
    {
        if (($data['clear_apisunat'] ?? false) === true) {
            $setting->apisunat_token_enc = null;
            $setting->apisunat_configurado = false;

            return;
        }

        if (! empty($data['apisunat_mode'])) {
            $setting->apisunat_mode = $data['apisunat_mode'];
        }

        if (! empty($data['apisunat_token'])) {
            $setting->apisunat_token_enc = Crypt::encryptString($data['apisunat_token']);
            $setting->apisunat_configurado = true;
        }
    }

    /**
     * Construye el payload que se expone al frontend.
     *
     * Importante: jamás devolvemos las credenciales en claro. En su
     * lugar exponemos flags booleanos `*_configurado` que el frontend
     * usa para indicar visualmente si la integración tiene clave guardada.
     *
     * @return array<string, mixed>
     */
    private function presentSetting(ClinicSetting $setting, bool $planPermiteFacturaElectronica): array
    {
        return [
            'id' => $setting->id,
            // Identidad
            'ruc' => $setting->ruc,
            'razon_social' => $setting->razon_social,
            'nombre_comercial' => $setting->nombre_comercial,
            'direccion_fiscal' => $setting->direccion_fiscal,
            'distrito_id' => $setting->distrito_id,
            'distrito_model' => $setting->distritoModel ? [
                'id' => $setting->distritoModel->id,
                'name' => $setting->distritoModel->name,
                'provincia_id' => $setting->distritoModel->provincia_id,
                'provincia' => [
                    'id' => $setting->distritoModel->provincia->id,
                    'name' => $setting->distritoModel->provincia->name,
                    'departamento_id' => $setting->distritoModel->provincia->departamento_id,
                    'departamento' => [
                        'id' => $setting->distritoModel->provincia->departamento->id,
                        'name' => $setting->distritoModel->provincia->departamento->name,
                    ],
                ],
            ] : null,
            // Branding
            'logo_url' => $setting->logo_url,
            'color_primario' => $setting->color_primario,
            'color_secundario' => $setting->color_secundario,
            // Contacto
            'email_institucional' => $setting->email_institucional,
            'telefono_principal' => $setting->telefono_principal,
            'web_url' => $setting->web_url,
            // Operación
            'duracion_cita_default_min' => $setting->duracion_cita_default_min,
            'intervalo_agenda_min' => $setting->intervalo_agenda_min,
            'agenda_hora_inicio' => $setting->agendaHoraInicio(),
            'agenda_hora_fin' => $setting->agendaHoraFin(),
            'dias_anticipacion_cita' => $setting->dias_anticipacion_cita,
            'horas_min_cancelacion' => $setting->horas_min_cancelacion,
            // Recordatorios
            'recordatorio_48h_activo' => $setting->recordatorio_48h_activo,
            'recordatorio_2h_activo' => $setting->recordatorio_2h_activo,
            'notificar_cita_whatsapp_activo' => $setting->notificarCitaWhatsAppActivo(),
            'notificar_grooming_creado_whatsapp_activo' => $setting->notificarGroomingWhatsAppActivo('programado'),
            'notificar_grooming_en_proceso_whatsapp_activo' => $setting->notificarGroomingWhatsAppActivo('en_proceso'),
            'notificar_grooming_completado_whatsapp_activo' => $setting->notificarGroomingWhatsAppActivo('completada'),
            'notificar_grooming_cancelado_whatsapp_activo' => $setting->notificarGroomingWhatsAppActivo('cancelada'),
            'notificar_grooming_no_asistio_whatsapp_activo' => $setting->notificarGroomingWhatsAppActivo('no_asistio'),
            'recordatorio_vacuna_activo' => $setting->recordatorio_vacuna_activo,
            'recordatorio_vacuna_dias_antes' => $setting->recordatorio_vacuna_dias_antes,
            'recordatorio_cumple_activo' => $setting->recordatorio_cumple_activo,
            // Facturación
            'moneda' => $setting->moneda,
            'igv_porcentaje' => (string) $setting->igv_porcentaje,
            'precio_incluye_igv' => $setting->precio_incluye_igv,
            'ticket_ancho_mm' => TicketAnchoMm::normalize((string) ($setting->ticket_ancho_mm ?? '')),
            'emite_comprobantes_sunat' => $planPermiteFacturaElectronica && (bool) $setting->emite_comprobantes_sunat,
            // APISUNAT: solo expuesto con plan que permite facturación (jamás token en claro).
            'apisunat_mode' => $planPermiteFacturaElectronica ? ($setting->apisunat_mode ?? 'sandbox') : 'sandbox',
            'apisunat_configurado' => $planPermiteFacturaElectronica && (bool) $setting->apisunat_configurado,
            // Remitente comercial visible
            'whatsapp_display_number' => $setting->whatsapp_display_number,
            'email_from' => $setting->email_from,
            'email_from_nombre' => $setting->email_from_nombre,
            // Audit trail
            'updated_at' => $setting->updated_at?->toIso8601String(),
            'actualizado_por' => $setting->actualizadoPor ? [
                'id' => $setting->actualizadoPor->id,
                'name' => $setting->actualizadoPor->name,
                'email' => $setting->actualizadoPor->email,
            ] : null,
        ];
    }
}
