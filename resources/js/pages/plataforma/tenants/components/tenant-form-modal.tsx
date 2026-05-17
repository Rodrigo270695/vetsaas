import { useForm, usePage } from '@inertiajs/react';
import { Loader2, Sparkles } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import {
    GeoCascadeFields,
    type GeoCascadeValue,
} from '@/components/geo/geo-cascade-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import tenants from '@/routes/plataforma/tenants';
import type { TenancyShared } from '@/types/tenancy';
import type { GeoOption, Tenant } from '../types';

export type TenantFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Tenant a editar; si es `null`, el modal abre en modo crear. */
    tenant: Tenant | null;
    /** Catálogo de departamentos para la cascada geo. */
    departamentos: readonly GeoOption[];
};

type TenantFormData = {
    slug: string;
    razon_social: string;
    nombre_comercial: string;
    ruc: string;
    email_admin: string;
    telefono: string;
    direccion: string;
    distrito_id: number | null;
    timezone: string;
    locale: string;
    canal_adquisicion: string;
    /** Solo se usa en create: define el trial inicial. */
    trial_days: number;
};

const DEFAULT_TRIAL_DAYS = 14;

const emptyForm: TenantFormData = {
    slug: '',
    razon_social: '',
    nombre_comercial: '',
    ruc: '',
    email_admin: '',
    telefono: '',
    direccion: '',
    distrito_id: null,
    timezone: 'America/Lima',
    locale: 'es_PE',
    canal_adquisicion: '',
    trial_days: DEFAULT_TRIAL_DAYS,
};

const buildInitialData = (tenant: Tenant | null): TenantFormData => ({
    slug: tenant?.slug ?? '',
    razon_social: tenant?.razon_social ?? '',
    nombre_comercial: tenant?.nombre_comercial ?? '',
    ruc: tenant?.ruc ?? '',
    email_admin: tenant?.email_admin ?? '',
    telefono: tenant?.telefono ?? '',
    direccion: tenant?.direccion ?? '',
    distrito_id: tenant?.distrito_id ?? null,
    timezone: tenant?.timezone ?? 'America/Lima',
    locale: tenant?.locale ?? 'es_PE',
    canal_adquisicion: tenant?.canal_adquisicion ?? '',
    trial_days: DEFAULT_TRIAL_DAYS,
});

/**
 * Deriva el estado inicial de la cascada geo a partir del tenant.
 * Mismo patrón que SedeFormModal.
 */
const buildInitialGeoValue = (tenant: Tenant | null): GeoCascadeValue => {
    if (!tenant || !tenant.distrito_model) {
        return {
            departamento_id: null,
            provincia_id: null,
            distrito_id: tenant?.distrito_id ?? null,
        };
    }
    return {
        departamento_id: tenant.distrito_model.provincia.departamento_id,
        provincia_id: tenant.distrito_model.provincia_id,
        distrito_id: tenant.distrito_model.id,
    };
};

const SLUG_REGEX = /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/;
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const RUC_REGEX = /^\d{11}$/;

const isFormValid = (data: TenantFormData): boolean => {
    if (!SLUG_REGEX.test(data.slug) || data.slug.length < 3) return false;
    if (data.razon_social.trim().length < 2) return false;
    if (!EMAIL_REGEX.test(data.email_admin.trim())) return false;
    if (data.ruc !== '' && !RUC_REGEX.test(data.ruc)) return false;
    if (data.timezone.trim() === '') return false;
    if (data.locale.trim() === '') return false;
    return true;
};

/**
 * Modal de crear/editar tenant.
 *
 * Espejo de `SedeFormModal` y `UserFormModal`:
 *   - Misma `FormSection` con título/hint + grilla 2 columnas.
 *   - En create, el slug define el subdominio (`{slug}.vetsaas.com`)
 *     y el schema físico (`vet_<slug_normalizado>`).
 *   - El estado `trial_days` solo se usa en create para definir cuándo
 *     vence el periodo de prueba inicial.
 *   - La ubicación es una cascada Departamento → Provincia → Distrito
 *     (opcional); solo `distrito_id` se envía al backend.
 *
 * Lo que NO se controla desde acá:
 *   - El estado (`trial → active → suspended → cancelled`): tiene
 *     endpoints dedicados (`suspend`/`resume`/`destroy`).
 *   - El onboarding (paso 0..5, `onboarding_completado`): lo maneja el
 *     wizard del cliente, no el admin.
 *   - El schema_name: lo deriva el backend del slug.
 */
export function TenantFormModal({
    open,
    onOpenChange,
    tenant,
    departamentos,
}: TenantFormModalProps) {
    const { t } = useTranslation(['tenants', 'common']);
    const tenancy = usePage().props.tenancy as TenancyShared;
    const isEdit = tenant !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<TenantFormData>(emptyForm);

    const subdomainVars = {
        slug: data.slug.trim() || 'mi-clinica',
        domain: tenancy.root_domain,
    };

    const canSubmit = isFormValid(data) && !processing;

    const initialSnapshotRef = useRef<TenantFormData>(emptyForm);

    /**
     * El componente GeoCascadeFields se autocontiene: maneja sus 3
     * niveles internos y nos notifica solo cuando cambia `distrito_id`,
     * que es lo único que viaja al backend.
     */
    const [geo, setGeo] = useState<GeoCascadeValue>(() =>
        buildInitialGeoValue(tenant),
    );

    useEffect(() => {
        if (open) {
            const initial = buildInitialData(tenant);
            initialSnapshotRef.current = initial;
            (Object.keys(initial) as Array<keyof TenantFormData>).forEach(
                (key) => {
                    setData(key, initial[key] as never);
                },
            );
            setGeo(buildInitialGeoValue(tenant));
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, tenant?.id]);

    const handleGeoChange = (next: GeoCascadeValue) => {
        setGeo(next);
        setData('distrito_id', next.distrito_id);
    };

    const isDirty = useMemo(() => {
        const initial = initialSnapshotRef.current;
        return (
            initial.slug !== data.slug ||
            initial.razon_social !== data.razon_social ||
            initial.nombre_comercial !== data.nombre_comercial ||
            initial.ruc !== data.ruc ||
            initial.email_admin !== data.email_admin ||
            initial.telefono !== data.telefono ||
            initial.direccion !== data.direccion ||
            initial.distrito_id !== data.distrito_id ||
            initial.timezone !== data.timezone ||
            initial.locale !== data.locale ||
            initial.canal_adquisicion !== data.canal_adquisicion ||
            (!isEdit && initial.trial_days !== data.trial_days)
        );
    }, [data, isEdit]);

    const confirmDiscard = (): boolean => {
        if (!isDirty) return true;
        return window.confirm(t('common:form.unsaved_changes'));
    };

    const handleClose = (next: boolean) => {
        if (!next) {
            if (!confirmDiscard()) {
                return;
            }
            reset();
            setGeo({
                departamento_id: null,
                provincia_id: null,
                distrito_id: null,
            });
            clearErrors();
        }
        onOpenChange(next);
    };

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const onSuccess = () => {
            reset();
            setGeo({
                departamento_id: null,
                provincia_id: null,
                distrito_id: null,
            });
            clearErrors();
            onOpenChange(false);
        };

        if (isEdit && tenant) {
            put(tenants.update(tenant.id).url, {
                preserveScroll: true,
                onSuccess,
            });
        } else {
            post(tenants.store().url, {
                preserveScroll: true,
                onSuccess,
            });
        }
    };

    return (
        <FormModal
            open={open}
            onOpenChange={handleClose}
            title={
                isEdit
                    ? t('tenants:form.title_edit')
                    : t('tenants:form.title_create')
            }
            description={
                isEdit
                    ? t('tenants:form.description_edit')
                    : t('tenants:form.description_create', subdomainVars)
            }
            size="lg"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleClose(false)}
                        disabled={processing}
                        className="cursor-pointer"
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={!canSubmit}
                        className="cursor-pointer gap-2 disabled:cursor-not-allowed"
                    >
                        {processing && (
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                        {isEdit
                            ? t('tenants:form.submit_edit')
                            : t('tenants:form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                <FormSection
                    index={0}
                    title={t('tenants:form.section_identity')}
                    description={t('tenants:form.section_identity_hint')}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="tenant-slug"
                            label={t('tenants:form.fields.slug')}
                            required
                            hint={t('tenants:form.fields.slug_hint', subdomainVars)}
                            error={errors.slug}
                        >
                            <Input
                                id="tenant-slug"
                                value={data.slug}
                                onChange={(e) =>
                                    setData(
                                        'slug',
                                        e.target.value
                                            .toLowerCase()
                                            .replace(/\s+/g, '-'),
                                    )
                                }
                                placeholder={t(
                                    'tenants:form.fields.slug_placeholder',
                                )}
                                autoComplete="off"
                                autoFocus
                                className="font-mono"
                            />
                        </FormField>

                        <FormField
                            id="tenant-razon-social"
                            label={t('tenants:form.fields.razon_social')}
                            required
                            error={errors.razon_social}
                        >
                            <Input
                                id="tenant-razon-social"
                                value={data.razon_social}
                                onChange={(e) =>
                                    setData('razon_social', e.target.value)
                                }
                                placeholder={t(
                                    'tenants:form.fields.razon_social_placeholder',
                                )}
                                autoComplete="off"
                            />
                        </FormField>

                        <FormField
                            id="tenant-nombre-comercial"
                            label={t('tenants:form.fields.nombre_comercial')}
                            error={errors.nombre_comercial}
                        >
                            <Input
                                id="tenant-nombre-comercial"
                                value={data.nombre_comercial}
                                onChange={(e) =>
                                    setData('nombre_comercial', e.target.value)
                                }
                                placeholder={t(
                                    'tenants:form.fields.nombre_comercial_placeholder',
                                )}
                                autoComplete="off"
                            />
                        </FormField>

                        <FormField
                            id="tenant-ruc"
                            label={t('tenants:form.fields.ruc')}
                            hint={t('tenants:form.fields.ruc_hint')}
                            error={errors.ruc}
                        >
                            <Input
                                id="tenant-ruc"
                                value={data.ruc}
                                onChange={(e) =>
                                    setData(
                                        'ruc',
                                        e.target.value.replace(/\D/g, ''),
                                    )
                                }
                                placeholder="20XXXXXXXXX"
                                autoComplete="off"
                                inputMode="numeric"
                                maxLength={11}
                                className="font-mono"
                            />
                        </FormField>
                    </div>
                </FormSection>

                <FormSection
                    index={1}
                    title={t('tenants:form.section_contact')}
                    description={t('tenants:form.section_contact_hint')}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="tenant-email-admin"
                            label={t('tenants:form.fields.email_admin')}
                            required
                            hint={t('tenants:form.fields.email_admin_hint')}
                            error={errors.email_admin}
                        >
                            <Input
                                id="tenant-email-admin"
                                type="email"
                                value={data.email_admin}
                                onChange={(e) =>
                                    setData('email_admin', e.target.value)
                                }
                                placeholder="admin@clinica.com"
                                autoComplete="off"
                            />
                        </FormField>

                        <FormField
                            id="tenant-telefono"
                            label={t('tenants:form.fields.telefono')}
                            error={errors.telefono}
                        >
                            <Input
                                id="tenant-telefono"
                                type="tel"
                                value={data.telefono}
                                onChange={(e) =>
                                    setData('telefono', e.target.value)
                                }
                                placeholder="+51 999 999 999"
                                autoComplete="off"
                            />
                        </FormField>

                        <div className="sm:col-span-2">
                            <FormField
                                id="tenant-direccion"
                                label={t('tenants:form.fields.direccion')}
                                error={errors.direccion}
                            >
                                <Input
                                    id="tenant-direccion"
                                    value={data.direccion}
                                    onChange={(e) =>
                                        setData('direccion', e.target.value)
                                    }
                                    placeholder={t(
                                        'tenants:form.fields.direccion_placeholder',
                                    )}
                                    autoComplete="off"
                                />
                            </FormField>
                        </div>

                        <div className="sm:col-span-2">
                            <GeoCascadeFields
                                departamentos={departamentos}
                                value={geo}
                                onChange={handleGeoChange}
                                errors={{
                                    distrito_id: errors.distrito_id,
                                }}
                                disabled={processing}
                                labels={{
                                    departamento: t(
                                        'tenants:form.fields.departamento',
                                    ),
                                    provincia: t(
                                        'tenants:form.fields.provincia',
                                    ),
                                    distrito: t(
                                        'tenants:form.fields.distrito',
                                    ),
                                }}
                            />
                        </div>
                    </div>
                </FormSection>

                <FormSection
                    index={2}
                    title={t('tenants:form.section_platform')}
                    description={t('tenants:form.section_platform_hint')}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            id="tenant-timezone"
                            label={t('tenants:form.fields.timezone')}
                            required
                            hint={t('tenants:form.fields.timezone_hint')}
                            error={errors.timezone}
                        >
                            <Input
                                id="tenant-timezone"
                                value={data.timezone}
                                onChange={(e) =>
                                    setData('timezone', e.target.value)
                                }
                                placeholder="America/Lima"
                                autoComplete="off"
                                className="font-mono"
                            />
                        </FormField>

                        <FormField
                            id="tenant-locale"
                            label={t('tenants:form.fields.locale')}
                            required
                            hint={t('tenants:form.fields.locale_hint')}
                            error={errors.locale}
                        >
                            <Input
                                id="tenant-locale"
                                value={data.locale}
                                onChange={(e) =>
                                    setData('locale', e.target.value)
                                }
                                placeholder="es_PE"
                                autoComplete="off"
                                className="font-mono"
                            />
                        </FormField>

                        <FormField
                            id="tenant-canal"
                            label={t('tenants:form.fields.canal')}
                            hint={t('tenants:form.fields.canal_hint')}
                            error={errors.canal_adquisicion}
                        >
                            <Input
                                id="tenant-canal"
                                value={data.canal_adquisicion}
                                onChange={(e) =>
                                    setData('canal_adquisicion', e.target.value)
                                }
                                placeholder={t(
                                    'tenants:form.fields.canal_placeholder',
                                )}
                                autoComplete="off"
                            />
                        </FormField>

                        {!isEdit && (
                            <FormField
                                id="tenant-trial-days"
                                label={t('tenants:form.fields.trial_days')}
                                hint={t('tenants:form.fields.trial_days_hint')}
                                error={errors.trial_days}
                            >
                                <div className="relative">
                                    <Input
                                        id="tenant-trial-days"
                                        type="number"
                                        min={0}
                                        max={365}
                                        value={data.trial_days}
                                        onChange={(e) =>
                                            setData(
                                                'trial_days',
                                                Number(e.target.value) || 0,
                                            )
                                        }
                                        className="pr-10"
                                    />
                                    <Sparkles
                                        className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-amber-500/80"
                                        strokeWidth={2.5}
                                        aria-hidden="true"
                                    />
                                </div>
                            </FormField>
                        )}
                    </div>
                </FormSection>
            </div>
        </FormModal>
    );
}
