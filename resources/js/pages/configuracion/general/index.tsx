import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/react';
import {
    Bell,
    Building2,
    CalendarClock,
    CheckCircle2,
    Eye,
    EyeOff,
    Info,
    KeyRound,
    Loader2,
    Megaphone,
    Palette,
    Phone,
    Receipt,
    Save,
    ShieldCheck,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader, StatBadge } from '@/components/data-page';
import { FormField, FormSection } from '@/components/forms';
import { GeoCascadeFields } from '@/components/geo/geo-cascade-fields';
import type { GeoCascadeValue } from '@/components/geo/geo-cascade-fields';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toastManager } from '@/lib/toast';
import general from '@/routes/configuracion/general';
import { LogoUploader } from './components/logo-uploader';
import { SectionCard } from './components/section-card';
import type { ClinicHeaderSnapshot, ClinicSetting, GeoOption } from './types';

type ConfiguracionGeneralProps = {
    setting: ClinicSetting;
    clinic_header: ClinicHeaderSnapshot;
    departamentos: readonly GeoOption[];
    plan_permite_factura_electronica: boolean;
};

/**
 * Shape del formulario de configuración (campos no-archivo).
 *
 * El logo viaja aparte como `File` en el state local: Inertia
 * `router.post` se encarga de serializarlo como `multipart/form-data`
 * junto con el resto del payload, así no metemos `File` dentro de
 * `useForm` (que mantiene su data como JSON para diffs).
 *
 * Notas:
 *   - Las credenciales sensibles de APISUNAT (`apisunat_token`) NO
 *     vienen del backend. El usuario las escribe en claro y, al guardar,
 *     el controller las cifra.
 *   - `clear_apisunat` y `clear_logo` marcan "borrar credencial/archivo
 *     existente": permiten distinguir "no tocó" de "borró".
 */
type FormState = {
    // Identidad
    ruc: string;
    razon_social: string;
    nombre_comercial: string;
    direccion_fiscal: string;
    distrito_id: number | null;
    // Branding (logo va aparte, ver useState)
    color_primario: string;
    color_secundario: string;
    // Contacto
    email_institucional: string;
    telefono_principal: string;
    web_url: string;
    // Operación
    duracion_cita_default_min: number;
    intervalo_agenda_min: number;
    agenda_hora_inicio: string;
    agenda_hora_fin: string;
    dias_anticipacion_cita: number;
    horas_min_cancelacion: number;
    // Recordatorios
    recordatorio_48h_activo: boolean;
    recordatorio_2h_activo: boolean;
    notificar_cita_whatsapp_activo: boolean;
    recordatorio_vacuna_activo: boolean;
    recordatorio_vacuna_dias_antes: number;
    recordatorio_cumple_activo: boolean;
    // Facturación
    moneda: 'PEN' | 'USD';
    igv_porcentaje: string;
    precio_incluye_igv: boolean;
    ticket_ancho_mm: '56' | '58' | '80';
    emite_comprobantes_sunat: boolean;
    // APISUNAT
    apisunat_token: string;
    apisunat_mode: 'sandbox' | 'produccion';
    // Remitente comercial visible
    whatsapp_display_number: string;
    email_from: string;
    email_from_nombre: string;
};

const buildInitialState = (setting: ClinicSetting): FormState => ({
    ruc: setting.ruc ?? '',
    razon_social: setting.razon_social ?? '',
    nombre_comercial: setting.nombre_comercial ?? '',
    direccion_fiscal: setting.direccion_fiscal ?? '',
    distrito_id: setting.distrito_id ?? null,
    color_primario: setting.color_primario ?? '',
    color_secundario: setting.color_secundario ?? '',
    email_institucional: setting.email_institucional ?? '',
    telefono_principal: setting.telefono_principal ?? '',
    web_url: setting.web_url ?? '',
    duracion_cita_default_min: setting.duracion_cita_default_min,
    intervalo_agenda_min: setting.intervalo_agenda_min,
    agenda_hora_inicio: setting.agenda_hora_inicio ?? '07:00',
    agenda_hora_fin: setting.agenda_hora_fin ?? '20:00',
    dias_anticipacion_cita: setting.dias_anticipacion_cita,
    horas_min_cancelacion: setting.horas_min_cancelacion,
    recordatorio_48h_activo: setting.recordatorio_48h_activo,
    recordatorio_2h_activo: setting.recordatorio_2h_activo,
    notificar_cita_whatsapp_activo:
        setting.notificar_cita_whatsapp_activo ?? true,
    recordatorio_vacuna_activo: setting.recordatorio_vacuna_activo,
    recordatorio_vacuna_dias_antes: setting.recordatorio_vacuna_dias_antes,
    recordatorio_cumple_activo: setting.recordatorio_cumple_activo,
    moneda: setting.moneda,
    igv_porcentaje: setting.igv_porcentaje,
    precio_incluye_igv: setting.precio_incluye_igv,
    ticket_ancho_mm:
        setting.ticket_ancho_mm === '56' ||
        setting.ticket_ancho_mm === '58' ||
        setting.ticket_ancho_mm === '80'
            ? setting.ticket_ancho_mm
            : '58',
    emite_comprobantes_sunat: setting.emite_comprobantes_sunat,
    apisunat_token: '',
    apisunat_mode: setting.apisunat_mode ?? 'sandbox',
    whatsapp_display_number: setting.whatsapp_display_number ?? '',
    email_from: setting.email_from ?? '',
    email_from_nombre: setting.email_from_nombre ?? '',
});

const buildInitialGeo = (setting: ClinicSetting): GeoCascadeValue => {
    if (!setting.distrito_model) {
        return {
            departamento_id: null,
            provincia_id: null,
            distrito_id: setting.distrito_id ?? null,
        };
    }

    return {
        departamento_id: setting.distrito_model.provincia.departamento_id,
        provincia_id: setting.distrito_model.provincia_id,
        distrito_id: setting.distrito_model.id,
    };
};

/**
 * Página de Configuración → General.
 *
 * Pantalla de edición de la (única) fila de `cfg_clinic_settings` del
 * tenant activo. Sigue el mismo lenguaje visual que el resto del panel:
 *
 *  - PageHeader con título, descripción y badges de estado (identidad,
 *    branding, facturación). La acción primaria vive solo en el footer
 *    sticky.
 *  - Tarjetas (`SectionCard`) por bloque temático: Identidad, Contacto,
 *    Branding (con LogoUploader), Operación, Recordatorios, Facturación
 *    electrónica (Nubefact), Remitente comercial.
 *  - El envío usa `router.post` con `_method=PUT` para soportar
 *    `multipart/form-data` con el archivo del logo.
 *  - i18n vía namespace `general` (común + dominio propio).
 */
export default function Index({
    setting,
    clinic_header,
    departamentos,
    plan_permite_factura_electronica,
}: ConfiguracionGeneralProps) {
    const { t } = useTranslation(['general', 'common']);
    const { can } = usePermission();
    const canUpdate = can('config-general.update');

    const [data, setDataInternal] = useState<FormState>(() =>
        buildInitialState(setting),
    );
    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [clearLogo, setClearLogo] = useState(false);
    const [clearApisunat, setClearApisunat] = useState(false);
    const [showApisunatToken, setShowApisunatToken] = useState(false);
    const [geo, setGeo] = useState<GeoCascadeValue>(() =>
        buildInitialGeo(setting),
    );
    const [errors, setErrors] = useState<Partial<Record<string, string>>>({});
    const [processing, setProcessing] = useState(false);
    const [recentlySuccessful, setRecentlySuccessful] = useState(false);
    const recentSuccessTimerRef = useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );

    /*
     * Re-hidrata el form cuando el controller emite un nuevo snapshot
     * (típicamente tras un guardado exitoso): los nuevos datos llegan
     * via props sin recargar la página.
     */
    /* eslint-disable react-hooks/set-state-in-effect -- sync local form state with Inertia props after save */
    useEffect(() => {
        setDataInternal(buildInitialState(setting));
        setGeo(buildInitialGeo(setting));
        setLogoFile(null);
        setClearLogo(false);
        setClearApisunat(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [setting.updated_at]);
    /* eslint-enable react-hooks/set-state-in-effect */

    useEffect(() => {
        return () => {
            if (recentSuccessTimerRef.current) {
                clearTimeout(recentSuccessTimerRef.current);
            }
        };
    }, []);

    const setData = useCallback(
        <K extends keyof FormState>(key: K, value: FormState[K]) => {
            setDataInternal((current) => ({ ...current, [key]: value }));
        },
        [],
    );

    const handleGeoChange = (next: GeoCascadeValue) => {
        setGeo(next);
        setData('distrito_id', next.distrito_id);
    };

    const handleLogoSelect = (file: File) => {
        setLogoFile(file);
        setClearLogo(false);
    };

    const handleLogoClearSelection = () => setLogoFile(null);
    const handleLogoTogglePendingRemoval = () => setClearLogo((c) => !c);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        // Construimos el payload manualmente porque queremos enviar
        // multipart/form-data (con el archivo del logo si existe).
        // Inertia detecta automáticamente cuando hay un File en el
        // payload y serializa todo como FormData.
        const payload: Record<string, FormDataConvertible> = {
            _method: 'put',
            ...data,
            // Distrito puede ser null; lo mandamos como string vacío
            // (FormData no soporta null y null no satisface Convertible).
            distrito_id: data.distrito_id ?? '',
            // Booleans → '1' / '0' para que sobrevivan al multipart.
            recordatorio_48h_activo: data.recordatorio_48h_activo ? 1 : 0,
            recordatorio_2h_activo: data.recordatorio_2h_activo ? 1 : 0,
            notificar_cita_whatsapp_activo:
                data.notificar_cita_whatsapp_activo ? 1 : 0,
            recordatorio_vacuna_activo: data.recordatorio_vacuna_activo ? 1 : 0,
            recordatorio_cumple_activo: data.recordatorio_cumple_activo ? 1 : 0,
            precio_incluye_igv: data.precio_incluye_igv ? 1 : 0,
            emite_comprobantes_sunat: data.emite_comprobantes_sunat ? 1 : 0,
            clear_logo: clearLogo ? 1 : 0,
            clear_apisunat: clearApisunat ? 1 : 0,
            ...(data.apisunat_token
                ? { apisunat_token: data.apisunat_token }
                : {}),
            apisunat_mode: data.apisunat_mode,
        };

        if (logoFile) {
            payload.logo = logoFile;
        }

        const logoChanged = Boolean(logoFile) || clearLogo;
        const colorsChanged =
            (data.color_primario ?? '') !== (setting.color_primario ?? '') ||
            (data.color_secundario ?? '') !== (setting.color_secundario ?? '');
        const brandingChanged = logoChanged || colorsChanged;

        setProcessing(true);
        router.post(general.update().url, payload, {
            preserveScroll: true,
            forceFormData: true,
            onError: (errs) => {
                setErrors(errs as Partial<Record<string, string>>);
            },
            onSuccess: () => {
                setErrors({});
                setRecentlySuccessful(true);

                if (brandingChanged) {
                    if (logoChanged) {
                        setLogoFile(null);
                        setClearLogo(false);
                    }

                    router.reload({
                        only: ['clinic_branding', 'setting'],
                    });

                    if (logoChanged) {
                        toastManager.success({
                            title: t('fields.logo_sidebar_updated'),
                        });
                    }
                }

                if (recentSuccessTimerRef.current) {
                    clearTimeout(recentSuccessTimerRef.current);
                }

                recentSuccessTimerRef.current = setTimeout(() => {
                    setRecentlySuccessful(false);
                }, 2500);
            },
            onFinish: () => setProcessing(false),
        });
    };

    /*
     * Resumen ejecutivo del estado de configuración: badges del header.
     */
    const stats = useMemo(() => {
        const identidadCompleta = Boolean(
            setting.ruc &&
            setting.razon_social &&
            setting.direccion_fiscal &&
            setting.distrito_id,
        );

        const contactoCompleto = Boolean(
            setting.email_institucional && setting.telefono_principal,
        );

        const brandingCompleto = Boolean(
            setting.logo_url && setting.color_primario,
        );

        return {
            identidadCompleta,
            contactoCompleto,
            brandingCompleto,
            facturacionConfigurada: setting.emite_comprobantes_sunat,
        };
    }, [setting]);

    const headerTitle =
        clinic_header?.nombre_comercial ?? clinic_header?.razon_social ?? '';
    const headerDescription = headerTitle
        ? t('description_for', { name: headerTitle })
        : t('description');

    return (
        <>
            <Head title={t('title')} />

            <form
                onSubmit={handleSubmit}
                className="flex flex-1 flex-col gap-5 p-4 pb-24 sm:p-6 sm:pb-24"
                noValidate
                encType="multipart/form-data"
            >
                <PageHeader
                    title={t('title')}
                    description={headerDescription}
                    stats={[
                        {
                            label: t('stats.identidad'),
                            value: stats.identidadCompleta
                                ? t('common:state.complete')
                                : t('common:state.incomplete'),
                            variant: stats.identidadCompleta
                                ? 'success'
                                : 'warning',
                            icon: Building2,
                        },
                        {
                            label: t('stats.contacto'),
                            value: stats.contactoCompleto
                                ? t('common:state.complete')
                                : t('common:state.incomplete'),
                            variant: stats.contactoCompleto
                                ? 'success'
                                : 'warning',
                            icon: Phone,
                        },
                        {
                            label: t('stats.branding'),
                            value: stats.brandingCompleto
                                ? t('common:state.complete')
                                : t('common:state.incomplete'),
                            variant: stats.brandingCompleto
                                ? 'success'
                                : 'muted',
                            icon: Palette,
                        },
                        {
                            label: t('stats.facturacion'),
                            value: stats.facturacionConfigurada
                                ? t('common:state.configured')
                                : t('common:state.not_configured'),
                            variant: stats.facturacionConfigurada
                                ? 'success'
                                : 'muted',
                            icon: stats.facturacionConfigurada
                                ? CheckCircle2
                                : XCircle,
                        },
                    ]}
                />

                {/* Nota explicativa: WhatsApp/correo los gestiona el SaaS */}
                <div className="flex items-start gap-3 rounded-lg border border-primary/20 bg-primary/5 p-4 text-sm">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/15 text-primary ring-1 ring-primary/20">
                        <Info className="size-4" strokeWidth={2.25} />
                    </span>
                    <div className="flex flex-col gap-1">
                        <span className="font-semibold">
                            {t('platform_note.title')}
                        </span>
                        <span className="text-xs leading-relaxed text-muted-foreground">
                            {t('platform_note.body')}
                        </span>
                    </div>
                </div>

                {/* ───── Identidad fiscal ───── */}
                <SectionCard
                    icon={Building2}
                    title={t('sections.identidad.title')}
                    description={t('sections.identidad.description')}
                >
                    <FormSection
                        index={0}
                        title=""
                        columns={2}
                        className="gap-0"
                    >
                        <FormField
                            id="general-ruc"
                            label={t('fields.ruc')}
                            error={errors.ruc}
                            hint={t('fields.ruc_hint')}
                        >
                            <Input
                                id="general-ruc"
                                value={data.ruc}
                                onChange={(e) =>
                                    setData(
                                        'ruc',
                                        e.target.value
                                            .replace(/\D/g, '')
                                            .slice(0, 11),
                                    )
                                }
                                placeholder="20123456789"
                                maxLength={11}
                                inputMode="numeric"
                                className="font-mono tabular-nums"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="general-razon-social"
                            label={t('fields.razon_social')}
                            error={errors.razon_social}
                        >
                            <Input
                                id="general-razon-social"
                                value={data.razon_social}
                                onChange={(e) =>
                                    setData('razon_social', e.target.value)
                                }
                                placeholder="Clínica Veterinaria San Patricio SAC"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="general-nombre-comercial"
                            label={t('fields.nombre_comercial')}
                            error={errors.nombre_comercial}
                            hint={t('fields.nombre_comercial_hint')}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="general-nombre-comercial"
                                value={data.nombre_comercial}
                                onChange={(e) =>
                                    setData('nombre_comercial', e.target.value)
                                }
                                placeholder="San Patricio Vet"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="general-direccion-fiscal"
                            label={t('fields.direccion_fiscal')}
                            error={errors.direccion_fiscal}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="general-direccion-fiscal"
                                value={data.direccion_fiscal}
                                onChange={(e) =>
                                    setData('direccion_fiscal', e.target.value)
                                }
                                placeholder="Av. Javier Prado Este 1234, San Isidro"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <div className="sm:col-span-2">
                            <GeoCascadeFields
                                departamentos={departamentos}
                                value={geo}
                                onChange={handleGeoChange}
                                disabled={!canUpdate || processing}
                                errors={{ distrito_id: errors.distrito_id }}
                                labels={{
                                    departamento: t('fields.departamento'),
                                    provincia: t('fields.provincia'),
                                    distrito: t('fields.distrito'),
                                }}
                            />
                        </div>
                    </FormSection>
                </SectionCard>

                {/* ───── Contacto ───── */}
                <SectionCard
                    icon={Phone}
                    title={t('sections.contacto.title')}
                    description={t('sections.contacto.description')}
                >
                    <FormSection
                        index={1}
                        title=""
                        columns={2}
                        className="gap-0"
                    >
                        <FormField
                            id="general-email-institucional"
                            label={t('fields.email_institucional')}
                            error={errors.email_institucional}
                        >
                            <Input
                                id="general-email-institucional"
                                type="email"
                                value={data.email_institucional}
                                onChange={(e) =>
                                    setData(
                                        'email_institucional',
                                        e.target.value,
                                    )
                                }
                                placeholder="contacto@miclinica.pe"
                                autoComplete="email"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="general-telefono-principal"
                            label={t('fields.telefono_principal')}
                            error={errors.telefono_principal}
                        >
                            <Input
                                id="general-telefono-principal"
                                value={data.telefono_principal}
                                onChange={(e) =>
                                    setData(
                                        'telefono_principal',
                                        e.target.value,
                                    )
                                }
                                placeholder="+51 1 555-0101"
                                autoComplete="tel"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="general-web-url"
                            label={t('fields.web_url')}
                            error={errors.web_url}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="general-web-url"
                                type="url"
                                value={data.web_url}
                                onChange={(e) =>
                                    setData('web_url', e.target.value)
                                }
                                placeholder="https://miclinica.pe"
                                autoComplete="url"
                                disabled={!canUpdate}
                            />
                        </FormField>
                    </FormSection>
                </SectionCard>

                {/* ───── Branding ───── */}
                <SectionCard
                    icon={Palette}
                    title={t('sections.branding.title')}
                    description={t('sections.branding.description')}
                >
                    <FormSection
                        index={2}
                        title=""
                        columns={2}
                        className="gap-0"
                    >
                        <FormField
                            id="general-logo"
                            label={t('fields.logo')}
                            error={errors.logo}
                            hint={t('fields.logo_hint')}
                            className="sm:col-span-2"
                        >
                            <LogoUploader
                                currentUrl={setting.logo_url}
                                file={logoFile}
                                pendingRemoval={clearLogo}
                                error={errors.logo}
                                canUpdate={canUpdate}
                                onSelect={handleLogoSelect}
                                onClearSelection={handleLogoClearSelection}
                                onTogglePendingRemoval={
                                    handleLogoTogglePendingRemoval
                                }
                            />
                        </FormField>

                        <FormField
                            id="general-color-primario"
                            label={t('fields.color_primario')}
                            error={errors.color_primario}
                        >
                            <div className="flex items-center gap-2">
                                <input
                                    type="color"
                                    aria-label={t('fields.color_primario')}
                                    value={data.color_primario || '#1f6f43'}
                                    onChange={(e) =>
                                        setData(
                                            'color_primario',
                                            e.target.value,
                                        )
                                    }
                                    disabled={!canUpdate}
                                    className="h-9 w-12 cursor-pointer rounded-md border border-border/60 bg-transparent disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <Input
                                    id="general-color-primario"
                                    value={data.color_primario}
                                    onChange={(e) =>
                                        setData(
                                            'color_primario',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="#1F6F43"
                                    maxLength={7}
                                    className="font-mono uppercase"
                                    disabled={!canUpdate}
                                />
                            </div>
                        </FormField>

                        <FormField
                            id="general-color-secundario"
                            label={t('fields.color_secundario')}
                            error={errors.color_secundario}
                        >
                            <div className="flex items-center gap-2">
                                <input
                                    type="color"
                                    aria-label={t('fields.color_secundario')}
                                    value={data.color_secundario || '#94c7a8'}
                                    onChange={(e) =>
                                        setData(
                                            'color_secundario',
                                            e.target.value,
                                        )
                                    }
                                    disabled={!canUpdate}
                                    className="h-9 w-12 cursor-pointer rounded-md border border-border/60 bg-transparent disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <Input
                                    id="general-color-secundario"
                                    value={data.color_secundario}
                                    onChange={(e) =>
                                        setData(
                                            'color_secundario',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="#94C7A8"
                                    maxLength={7}
                                    className="font-mono uppercase"
                                    disabled={!canUpdate}
                                />
                            </div>
                        </FormField>
                    </FormSection>
                </SectionCard>

                {/* ───── Operación: agenda y citas ───── */}
                <SectionCard
                    icon={CalendarClock}
                    title={t('sections.operacion.title')}
                    description={t('sections.operacion.description')}
                >
                    <FormSection
                        index={3}
                        title=""
                        columns={2}
                        className="gap-0"
                    >
                        <FormField
                            id="general-duracion-cita"
                            label={t('fields.duracion_cita_default_min')}
                            error={errors.duracion_cita_default_min}
                            hint={t('fields.duracion_cita_default_min_hint')}
                            required
                        >
                            <Input
                                id="general-duracion-cita"
                                type="number"
                                value={data.duracion_cita_default_min}
                                onChange={(e) =>
                                    setData(
                                        'duracion_cita_default_min',
                                        Number(e.target.value) || 0,
                                    )
                                }
                                min={5}
                                max={480}
                                className="tabular-nums"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="general-intervalo-agenda"
                            label={t('fields.intervalo_agenda_min')}
                            error={errors.intervalo_agenda_min}
                            hint={t('fields.intervalo_agenda_min_hint')}
                            required
                        >
                            <Input
                                id="general-intervalo-agenda"
                                type="number"
                                value={data.intervalo_agenda_min}
                                onChange={(e) =>
                                    setData(
                                        'intervalo_agenda_min',
                                        Number(e.target.value) || 0,
                                    )
                                }
                                min={5}
                                max={120}
                                className="tabular-nums"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="general-agenda-hora-inicio"
                            label={t('fields.agenda_hora_inicio')}
                            error={errors.agenda_hora_inicio}
                            hint={t('fields.agenda_hora_inicio_hint')}
                            required
                        >
                            <Input
                                id="general-agenda-hora-inicio"
                                type="time"
                                step={3600}
                                value={data.agenda_hora_inicio}
                                onChange={(e) =>
                                    setData(
                                        'agenda_hora_inicio',
                                        e.target.value,
                                    )
                                }
                                max={data.agenda_hora_fin}
                                className="tabular-nums"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="general-agenda-hora-fin"
                            label={t('fields.agenda_hora_fin')}
                            error={errors.agenda_hora_fin}
                            hint={t('fields.agenda_hora_fin_hint')}
                            required
                        >
                            <Input
                                id="general-agenda-hora-fin"
                                type="time"
                                step={3600}
                                value={data.agenda_hora_fin}
                                onChange={(e) =>
                                    setData('agenda_hora_fin', e.target.value)
                                }
                                min={data.agenda_hora_inicio}
                                className="tabular-nums"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="general-dias-anticipacion"
                            label={t('fields.dias_anticipacion_cita')}
                            error={errors.dias_anticipacion_cita}
                            hint={t('fields.dias_anticipacion_cita_hint')}
                            required
                        >
                            <Input
                                id="general-dias-anticipacion"
                                type="number"
                                value={data.dias_anticipacion_cita}
                                onChange={(e) =>
                                    setData(
                                        'dias_anticipacion_cita',
                                        Number(e.target.value) || 0,
                                    )
                                }
                                min={1}
                                max={365}
                                className="tabular-nums"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="general-horas-min-cancelacion"
                            label={t('fields.horas_min_cancelacion')}
                            error={errors.horas_min_cancelacion}
                            hint={t('fields.horas_min_cancelacion_hint')}
                            required
                        >
                            <Input
                                id="general-horas-min-cancelacion"
                                type="number"
                                value={data.horas_min_cancelacion}
                                onChange={(e) =>
                                    setData(
                                        'horas_min_cancelacion',
                                        Number(e.target.value) || 0,
                                    )
                                }
                                min={0}
                                max={168}
                                className="tabular-nums"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <div className="sm:col-span-2">
                            <ToggleRow
                                id="general-notificar-cita-whatsapp"
                                label={t(
                                    'fields.notificar_cita_whatsapp_activo',
                                )}
                                hint={t(
                                    'fields.notificar_cita_whatsapp_activo_hint',
                                )}
                                checked={data.notificar_cita_whatsapp_activo}
                                onChange={(value) =>
                                    setData(
                                        'notificar_cita_whatsapp_activo',
                                        value,
                                    )
                                }
                                disabled={!canUpdate}
                            />
                        </div>
                    </FormSection>
                </SectionCard>

                {/* ───── Recordatorios automáticos ───── */}
                <SectionCard
                    icon={Bell}
                    title={t('sections.recordatorios.title')}
                    description={t('sections.recordatorios.description')}
                >
                    <FormSection
                        index={4}
                        title=""
                        columns={1}
                        className="gap-0"
                    >
                        <ToggleRow
                            id="general-recordatorio-48h"
                            label={t('fields.recordatorio_48h_activo')}
                            hint={t('fields.recordatorio_48h_activo_hint')}
                            checked={data.recordatorio_48h_activo}
                            onChange={(v) =>
                                setData('recordatorio_48h_activo', v)
                            }
                            disabled={!canUpdate}
                        />
                        <ToggleRow
                            id="general-recordatorio-2h"
                            label={t('fields.recordatorio_2h_activo')}
                            hint={t('fields.recordatorio_2h_activo_hint')}
                            checked={data.recordatorio_2h_activo}
                            onChange={(v) =>
                                setData('recordatorio_2h_activo', v)
                            }
                            disabled={!canUpdate}
                        />
                        <ToggleRow
                            id="general-recordatorio-vacuna"
                            label={t('fields.recordatorio_vacuna_activo')}
                            hint={t('fields.recordatorio_vacuna_activo_hint')}
                            checked={data.recordatorio_vacuna_activo}
                            onChange={(v) =>
                                setData('recordatorio_vacuna_activo', v)
                            }
                            disabled={!canUpdate}
                        />
                        {data.recordatorio_vacuna_activo && (
                            <div className="mt-1 ml-12">
                                <FormField
                                    id="general-recordatorio-vacuna-dias"
                                    label={t(
                                        'fields.recordatorio_vacuna_dias_antes',
                                    )}
                                    error={
                                        errors.recordatorio_vacuna_dias_antes
                                    }
                                    hint={t(
                                        'fields.recordatorio_vacuna_dias_antes_hint',
                                    )}
                                    className="max-w-xs"
                                >
                                    <Input
                                        id="general-recordatorio-vacuna-dias"
                                        type="number"
                                        value={
                                            data.recordatorio_vacuna_dias_antes
                                        }
                                        onChange={(e) =>
                                            setData(
                                                'recordatorio_vacuna_dias_antes',
                                                Number(e.target.value) || 0,
                                            )
                                        }
                                        min={1}
                                        max={90}
                                        className="tabular-nums"
                                        disabled={!canUpdate}
                                    />
                                </FormField>
                            </div>
                        )}
                        <ToggleRow
                            id="general-recordatorio-cumple"
                            label={t('fields.recordatorio_cumple_activo')}
                            hint={t('fields.recordatorio_cumple_activo_hint')}
                            checked={data.recordatorio_cumple_activo}
                            onChange={(v) =>
                                setData('recordatorio_cumple_activo', v)
                            }
                            disabled={!canUpdate}
                        />
                    </FormSection>
                </SectionCard>

                {/* ───── Facturación ───── */}
                <SectionCard
                    icon={Receipt}
                    title={t('sections.facturacion.title')}
                    description={t('sections.facturacion.description')}
                    badge={
                        <StatBadge
                            label=""
                            value={
                                setting.emite_comprobantes_sunat
                                    ? t('common:state.configured')
                                    : t('common:state.not_configured')
                            }
                            variant={
                                setting.emite_comprobantes_sunat
                                    ? 'success'
                                    : 'muted'
                            }
                            icon={
                                setting.emite_comprobantes_sunat
                                    ? CheckCircle2
                                    : XCircle
                            }
                        />
                    }
                >
                    <FormSection
                        index={5}
                        title=""
                        columns={2}
                        className="gap-0"
                    >
                        <FormField
                            id="general-moneda"
                            label={t('fields.moneda')}
                            error={errors.moneda}
                            required
                        >
                            <Select
                                value={data.moneda}
                                onValueChange={(v) =>
                                    setData('moneda', v as 'PEN' | 'USD')
                                }
                                disabled={!canUpdate}
                            >
                                <SelectTrigger id="general-moneda">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="PEN">
                                        PEN — {t('fields.moneda_pen')}
                                    </SelectItem>
                                    <SelectItem value="USD">
                                        USD — {t('fields.moneda_usd')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            id="general-igv-porcentaje"
                            label={t('fields.igv_porcentaje')}
                            error={errors.igv_porcentaje}
                            hint={t('fields.igv_porcentaje_hint')}
                            required
                        >
                            <Input
                                id="general-igv-porcentaje"
                                type="number"
                                value={data.igv_porcentaje}
                                onChange={(e) =>
                                    setData('igv_porcentaje', e.target.value)
                                }
                                min={0}
                                max={100}
                                step={0.01}
                                className="tabular-nums"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <div className="sm:col-span-2">
                            <ToggleRow
                                id="general-precio-incluye-igv"
                                label={t('fields.precio_incluye_igv')}
                                hint={t('fields.precio_incluye_igv_hint')}
                                checked={data.precio_incluye_igv}
                                onChange={(v) =>
                                    setData('precio_incluye_igv', v)
                                }
                                disabled={!canUpdate}
                            />
                        </div>

                        <FormField
                            id="general-ticket-ancho-mm"
                            label={t('fields.ticket_ancho_mm')}
                            error={errors.ticket_ancho_mm}
                            hint={t('fields.ticket_ancho_mm_hint')}
                            className="sm:col-span-2"
                        >
                            <Select
                                value={data.ticket_ancho_mm}
                                onValueChange={(v) =>
                                    setData(
                                        'ticket_ancho_mm',
                                        v as '56' | '58' | '80',
                                    )
                                }
                                disabled={!canUpdate}
                            >
                                <SelectTrigger id="general-ticket-ancho-mm">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="56">
                                        {t('fields.ticket_ancho_mm_56')}
                                    </SelectItem>
                                    <SelectItem value="58">
                                        {t('fields.ticket_ancho_mm_58')}
                                    </SelectItem>
                                    <SelectItem value="80">
                                        {t('fields.ticket_ancho_mm_80')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <div className="flex flex-col gap-3 sm:col-span-2">
                            {!plan_permite_factura_electronica ? (
                                <div className="flex gap-2 rounded-lg border border-border/70 bg-muted/30 p-3 text-sm text-muted-foreground">
                                    <Info
                                        className="mt-0.5 size-4 shrink-0"
                                        aria-hidden
                                    />
                                    <div className="flex flex-col gap-1">
                                        <span className="font-medium text-foreground">
                                            {t(
                                                'plan_sin_facturacion_electronica.title',
                                            )}
                                        </span>
                                        <span>
                                            {t(
                                                'plan_sin_facturacion_electronica.body',
                                            )}
                                        </span>
                                    </div>
                                </div>
                            ) : null}
                            <ToggleRow
                                id="general-emite-sunat"
                                label={t('fields.emite_comprobantes_sunat')}
                                hint={t('fields.emite_comprobantes_sunat_hint')}
                                checked={data.emite_comprobantes_sunat}
                                onChange={(v) =>
                                    setData('emite_comprobantes_sunat', v)
                                }
                                disabled={
                                    !canUpdate ||
                                    !plan_permite_factura_electronica
                                }
                            />
                            {errors.emite_comprobantes_sunat ? (
                                <p
                                    className="text-xs text-destructive"
                                    role="alert"
                                >
                                    {errors.emite_comprobantes_sunat}
                                </p>
                            ) : null}
                        </div>

                        {/* APISUNAT: solo visible si el plan permite facturación */}
                        {plan_permite_factura_electronica && (
                            <div className="sm:col-span-2">
                                <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                                    <div className="mb-3 flex items-center gap-2">
                                        <span className="flex size-7 items-center justify-center rounded-md bg-primary/10 text-primary ring-1 ring-primary/20">
                                            <KeyRound
                                                className="size-4"
                                                strokeWidth={2}
                                            />
                                        </span>
                                        <div>
                                            <p className="text-sm font-semibold">
                                                Integración APISUNAT
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Configura el token de tu cuenta
                                                APISUNAT para emitir
                                                comprobantes electrónicos.
                                            </p>
                                        </div>
                                        {setting.apisunat_configurado && (
                                            <span className="ml-auto inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">
                                                <CheckCircle2 className="size-3" />
                                                Configurado
                                            </span>
                                        )}
                                    </div>

                                    <div className="flex flex-col gap-3">
                                        {/* Modo sandbox / producción */}
                                        <FormField
                                            id="apisunat-mode"
                                            label="Modo de operación"
                                            error={errors.apisunat_mode}
                                        >
                                            <Select
                                                value={data.apisunat_mode}
                                                onValueChange={(v) =>
                                                    setData(
                                                        'apisunat_mode',
                                                        v as
                                                            | 'sandbox'
                                                            | 'produccion',
                                                    )
                                                }
                                                disabled={!canUpdate}
                                            >
                                                <SelectTrigger id="apisunat-mode">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="sandbox">
                                                        Sandbox — pruebas (beta
                                                        SUNAT)
                                                    </SelectItem>
                                                    <SelectItem value="produccion">
                                                        Producción — emite
                                                        comprobantes reales
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </FormField>

                                        {/* Token APISUNAT */}
                                        <FormField
                                            id="apisunat-token"
                                            label={
                                                setting.apisunat_configurado
                                                    ? 'Nuevo token (dejar vacío para conservar el actual)'
                                                    : 'Token de APISUNAT'
                                            }
                                            error={errors.apisunat_token}
                                            hint="Lo encuentras en app.apisunat.pe → tu organización → API Token."
                                        >
                                            <div className="relative">
                                                <Input
                                                    id="apisunat-token"
                                                    type={
                                                        showApisunatToken
                                                            ? 'text'
                                                            : 'password'
                                                    }
                                                    value={data.apisunat_token}
                                                    onChange={(e) =>
                                                        setData(
                                                            'apisunat_token',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder={
                                                        setting.apisunat_configurado
                                                            ? '••••••••••••••••'
                                                            : 'eyJhbGciOiJIUzI1NiIs...'
                                                    }
                                                    className="pr-10 font-mono text-xs"
                                                    disabled={
                                                        !canUpdate ||
                                                        clearApisunat
                                                    }
                                                    autoComplete="off"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setShowApisunatToken(
                                                            (v) => !v,
                                                        )
                                                    }
                                                    className="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
                                                    tabIndex={-1}
                                                    aria-label={
                                                        showApisunatToken
                                                            ? 'Ocultar token'
                                                            : 'Mostrar token'
                                                    }
                                                >
                                                    {showApisunatToken ? (
                                                        <EyeOff className="size-4" />
                                                    ) : (
                                                        <Eye className="size-4" />
                                                    )}
                                                </button>
                                            </div>
                                        </FormField>

                                        {/* Borrar credencial */}
                                        {setting.apisunat_configurado &&
                                            canUpdate && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setClearApisunat(
                                                            (v) => !v,
                                                        )
                                                    }
                                                    className={`inline-flex items-center gap-1.5 self-start rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                                                        clearApisunat
                                                            ? 'bg-destructive/10 text-destructive ring-1 ring-destructive/30 hover:bg-destructive/20'
                                                            : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-destructive'
                                                    }`}
                                                >
                                                    <Trash2 className="size-3.5" />
                                                    {clearApisunat
                                                        ? 'Cancelar borrado'
                                                        : 'Borrar token guardado'}
                                                </button>
                                            )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </FormSection>
                </SectionCard>

                {/* ───── Remitente comercial (cómo verán los mensajes los clientes) ───── */}
                <SectionCard
                    icon={Megaphone}
                    title={t('sections.remitente.title')}
                    description={t('sections.remitente.description')}
                >
                    <FormSection
                        index={6}
                        title=""
                        columns={2}
                        className="gap-0"
                    >
                        <FormField
                            id="general-email-from-nombre"
                            label={t('fields.email_from_nombre')}
                            error={errors.email_from_nombre}
                            hint={t('fields.email_from_nombre_hint')}
                        >
                            <Input
                                id="general-email-from-nombre"
                                value={data.email_from_nombre}
                                onChange={(e) =>
                                    setData('email_from_nombre', e.target.value)
                                }
                                placeholder="Clínica San Patricio"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="general-email-from"
                            label={t('fields.email_from')}
                            error={errors.email_from}
                            hint={t('fields.email_from_hint')}
                        >
                            <Input
                                id="general-email-from"
                                type="email"
                                value={data.email_from}
                                onChange={(e) =>
                                    setData('email_from', e.target.value)
                                }
                                placeholder="contacto@miclinica.pe"
                                autoComplete="email"
                                disabled={!canUpdate}
                            />
                        </FormField>

                        <FormField
                            id="general-whatsapp-display"
                            label={t('fields.whatsapp_display_number')}
                            error={errors.whatsapp_display_number}
                            hint={t('fields.whatsapp_display_number_hint')}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="general-whatsapp-display"
                                value={data.whatsapp_display_number}
                                onChange={(e) =>
                                    setData(
                                        'whatsapp_display_number',
                                        e.target.value,
                                    )
                                }
                                placeholder="+51 999 000 111"
                                disabled={!canUpdate}
                            />
                        </FormField>
                    </FormSection>
                </SectionCard>

                {/*
                  Barra de acción sticky al pie del formulario. Contiene
                  el indicador de "última actualización" y el botón
                  principal "Guardar cambios". Es la ÚNICA acción primaria
                  de la página.
                */}
                {canUpdate && (
                    <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border/60 bg-card/95 px-4 py-3 backdrop-blur-md sm:px-6">
                        <div className="mx-auto flex max-w-7xl items-center justify-between gap-3">
                            <div className="flex min-w-0 items-center gap-2 text-xs text-muted-foreground">
                                <ShieldCheck
                                    className="size-4 shrink-0 text-primary/70"
                                    strokeWidth={2.25}
                                />
                                <span className="truncate">
                                    {setting.actualizado_por
                                        ? t('footer.last_updated_by', {
                                              name: setting.actualizado_por
                                                  .name,
                                          })
                                        : t('footer.never_updated')}
                                </span>
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="cursor-pointer gap-2 disabled:cursor-not-allowed"
                            >
                                {processing ? (
                                    <Loader2
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                ) : recentlySuccessful ? (
                                    <CheckCircle2
                                        className="size-4"
                                        strokeWidth={2.5}
                                    />
                                ) : (
                                    <Save
                                        className="size-4"
                                        strokeWidth={2.5}
                                    />
                                )}
                                {recentlySuccessful
                                    ? t('actions.saved')
                                    : t('actions.save')}
                            </Button>
                        </div>
                    </div>
                )}
            </form>
        </>
    );
}

/* -------------------------------------------------------------------------- */
/*                              Helper components                              */
/* -------------------------------------------------------------------------- */

type ToggleRowProps = {
    id: string;
    label: string;
    hint?: string;
    checked: boolean;
    onChange: (next: boolean) => void;
    disabled?: boolean;
};

/**
 * Fila tipo "toggle" con label, hint y checkbox.
 */
function ToggleRow({
    id,
    label,
    hint,
    checked,
    onChange,
    disabled,
}: ToggleRowProps) {
    return (
        <label
            htmlFor={id}
            className="flex cursor-pointer items-start gap-3 rounded-lg border border-border/60 bg-card/40 p-3 transition-colors hover:bg-muted/30 has-[input:disabled]:cursor-not-allowed has-[input:disabled]:opacity-60"
        >
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(c) => onChange(c === true)}
                disabled={disabled}
                className="mt-0.5"
            />
            <div className="flex flex-col gap-0.5">
                <span className="text-sm font-medium">{label}</span>
                {hint && (
                    <span className="text-xs text-muted-foreground">
                        {hint}
                    </span>
                )}
            </div>
        </label>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Configuración' },
            { title: 'General', href: '/configuracion/general' },
        ]}
    >
        {page}
    </AppLayout>
);
