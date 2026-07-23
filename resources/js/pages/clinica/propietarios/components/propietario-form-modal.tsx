import { useForm } from '@inertiajs/react';
import { Loader2, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { DocumentTypeSelect, FormField, FormModal, FormSection } from '@/components/forms';
import {
    GeoCascadeFields,
    type GeoCascadeValue,
} from '@/components/geo/geo-cascade-fields';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { isPropietarioDocumentTypeCode } from '@/lib/document-type-options';
import { enqueueIfOffline } from '@/lib/offline/enqueue-if-offline';
import { cn } from '@/lib/utils';
import { toastManager } from '@/lib/toast';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import propietarios from '@/routes/clinica/propietarios';
import type { GeoOption, Propietario } from '../types';

function soloDigitos(value: string, max?: number): string {
    const digits = value.replace(/\D/g, '');

    return max !== undefined ? digits.slice(0, max) : digits;
}

export type PropietarioCreatedPayload = {
    id: string;
    label: string;
    doc: string | null;
};

export type PropietarioFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    propietario: Propietario | null;
    departamentos: readonly GeoOption[];
    /** Si se define, crea vía JSON (caja) y llama onCreated en lugar de redirigir. */
    jsonStoreUrl?: string;
    onCreated?: (payload: PropietarioCreatedPayload) => void;
};

type FormData = {
    tipo_documento: string;
    numero_documento: string;
    nombres: string;
    apellidos: string;
    razon_social: string;
    email: string;
    telefono: string;
    telefono_alt: string;
    direccion: string;
    distrito_id: number | null;
    notas: string;
    activo: boolean;
};

const empty: FormData = {
    tipo_documento: 'DNI',
    numero_documento: '',
    nombres: '',
    apellidos: '',
    razon_social: '',
    email: '',
    telefono: '',
    telefono_alt: '',
    direccion: '',
    distrito_id: null,
    notas: '',
    activo: true,
};

function normalizeTipoDocumento(raw: string | null | undefined): string {
    if (!raw) {
        return '';
    }
    const u = raw.trim().toUpperCase();
    return isPropietarioDocumentTypeCode(u) ? u : '';
}

const fromModel = (p: Propietario | null): FormData => ({
    tipo_documento: p ? normalizeTipoDocumento(p.tipo_documento) : 'DNI',
    numero_documento: p?.numero_documento ?? '',
    nombres: p?.nombres ?? '',
    apellidos: p?.apellidos ?? '',
    razon_social: p?.razon_social ?? '',
    email: p?.email ?? '',
    telefono: p?.telefono ?? '',
    telefono_alt: p?.telefono_alt ?? '',
    direccion: p?.direccion ?? '',
    distrito_id: p?.distrito_id ?? null,
    notas: p?.notas ?? '',
    activo: p?.activo ?? true,
});

const geoFrom = (p: Propietario | null): GeoCascadeValue => {
    if (!p?.distrito_model) {
        return {
            departamento_id: null,
            provincia_id: null,
            distrito_id: p?.distrito_id ?? null,
        };
    }
    return {
        departamento_id: p.distrito_model.provincia.departamento_id,
        provincia_id: p.distrito_model.provincia_id,
        distrito_id: p.distrito_model.id,
    };
};

export function PropietarioFormModal({
    open,
    onOpenChange,
    propietario,
    departamentos,
    jsonStoreUrl,
    onCreated,
}: PropietarioFormModalProps) {
    const { t } = useTranslation(['propietarios', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const isEdit = propietario !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors, setError } =
        useForm<FormData>(empty);

    const [geo, setGeo] = useState<GeoCascadeValue>(() => geoFrom(null));
    const initialRef = useRef<FormData>(empty);
    const lastConsultaKeyRef = useRef<string | null>(null);
    const [jsonSubmitting, setJsonSubmitting] = useState(false);
    const [consultandoDoc, setConsultandoDoc] = useState(false);
    const submitting = processing || jsonSubmitting;
    const canSubmit = data.nombres.trim().length > 0 && !submitting;

    const tipoDoc = data.tipo_documento.trim().toUpperCase();
    const isDni = tipoDoc === 'DNI';
    const isRuc = tipoDoc === 'RUC';
    const isConsultableDoc = isDni || isRuc;
    const docMaxLen = isDni ? 8 : isRuc ? 11 : undefined;
    const docLen = soloDigitos(data.numero_documento).length;
    const docCompleto = docMaxLen !== undefined && docLen === docMaxLen;

    const consultaKeyFor = (tipo: string, numero: string, maxLen: number | undefined) => {
        if (maxLen === undefined) {
            return null;
        }

        const digits = soloDigitos(numero, maxLen);

        return digits.length === maxLen ? `${tipo}:${digits}` : null;
    };

    useEffect(() => {
        if (open) {
            const initial = fromModel(propietario);
            initialRef.current = initial;
            (Object.keys(initial) as Array<keyof FormData>).forEach((key) => {
                setData(key, initial[key]);
            });
            setGeo(geoFrom(propietario));
            clearErrors();
            setConsultandoDoc(false);

            const tipo = initial.tipo_documento.trim().toUpperCase();
            const max = tipo === 'DNI' ? 8 : tipo === 'RUC' ? 11 : undefined;
            // Evita auto-consulta al abrir un registro ya completo (edición).
            lastConsultaKeyRef.current = consultaKeyFor(tipo, initial.numero_documento, max);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, propietario?.id]);

    const handleGeoChange = (next: GeoCascadeValue) => {
        setGeo(next);
        setData('distrito_id', next.distrito_id);
    };

    const handleTipoDocumentoChange = (value: string) => {
        const upper = value.trim().toUpperCase();
        let numero = data.numero_documento;

        if (upper === 'DNI') {
            numero = soloDigitos(numero, 8);
        } else if (upper === 'RUC') {
            numero = soloDigitos(numero, 11);
        }

        lastConsultaKeyRef.current = null;
        setData({
            ...data,
            tipo_documento: value,
            numero_documento: numero,
        });
    };

    const onConsultarDocumento = async (forcedNumero?: string) => {
        const numero = soloDigitos(forcedNumero ?? data.numero_documento, docMaxLen);

        if (!isConsultableDoc || docMaxLen === undefined) {
            return;
        }

        if (numero.length !== docMaxLen) {
            toastManager.error({
                title: isDni ? t('form.consultar_invalid_dni') : t('form.consultar_invalid_ruc'),
            });

            return;
        }

        const key = `${tipoDoc}:${numero}`;
        lastConsultaKeyRef.current = key;
        setConsultandoDoc(true);

        try {
            const url = isRuc
                ? `${propietarios.consultaRuc.url()}?ruc=${encodeURIComponent(numero)}`
                : `${propietarios.consultaDni.url()}?dni=${encodeURIComponent(numero)}`;

            const res = await fetch(url, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const body = (await res.json()) as {
                success?: boolean;
                message?: string;
                code?: string;
                data?: {
                    dni?: string;
                    ruc?: string;
                    nombres?: string;
                    apellidos?: string;
                    razon_social?: string;
                    direccion?: string | null;
                };
            };

            if (!res.ok || !body.success || !body.data) {
                const title =
                    res.status === 429 || body.code === 'rate_limit'
                        ? t('form.consultar_rate_limit')
                        : (body.message ?? t('form.consultar_error'));
                toastManager.error({ title });

                return;
            }

            const d = body.data;

            if (isDni) {
                setData((prev) => ({
                    ...prev,
                    numero_documento: d.dni ?? numero,
                    nombres: typeof d.nombres === 'string' ? d.nombres : prev.nombres,
                    apellidos: typeof d.apellidos === 'string' ? d.apellidos : prev.apellidos,
                }));
            } else {
                setData((prev) => ({
                    ...prev,
                    numero_documento: d.ruc ?? numero,
                    razon_social:
                        typeof d.razon_social === 'string' ? d.razon_social : prev.razon_social,
                    direccion: typeof d.direccion === 'string' ? d.direccion : prev.direccion,
                    nombres:
                        prev.nombres.trim() !== ''
                            ? prev.nombres
                            : typeof d.razon_social === 'string'
                              ? d.razon_social
                              : prev.nombres,
                }));
            }
        } catch {
            toastManager.error({ title: t('form.consultar_error') });
        } finally {
            setConsultandoDoc(false);
        }
    };

    useEffect(() => {
        if (!open || !isConsultableDoc || !docCompleto || consultandoDoc || submitting) {
            return;
        }

        const key = consultaKeyFor(tipoDoc, data.numero_documento, docMaxLen);

        if (!key || lastConsultaKeyRef.current === key) {
            return;
        }

        void onConsultarDocumento(soloDigitos(data.numero_documento, docMaxLen));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, data.numero_documento, tipoDoc, docCompleto, consultandoDoc, submitting]);

    const isDirty = useMemo(() => {
        const initial = initialRef.current;
        return (Object.keys(initial) as Array<keyof FormData>).some(
            (key) => initial[key] !== data[key],
        );
    }, [data]);

    const handleClose = (next: boolean) => {
        if (!next) {
            if (
                isDirty &&
                !window.confirm(t('common:form.unsaved_changes'))
            ) {
                return;
            }
            reset();
            setGeo({ departamento_id: null, provincia_id: null, distrito_id: null });
            clearErrors();
        }
        onOpenChange(next);
    };

    const onSubmit = async (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const onSuccess = () => {
            reset();
            setGeo({ departamento_id: null, provincia_id: null, distrito_id: null });
            clearErrors();
            onOpenChange(false);
        };

        if (isEdit && propietario) {
            put(propietarios.update(propietario.id).url, {
                preserveScroll: true,
                onSuccess,
            });

            return;
        }

        if (jsonStoreUrl && onCreated) {
            const offlinePayload = {
                ...data,
                tipo_documento: normalizeTipoDocumento(data.tipo_documento) || null,
            };
            const queued = await enqueueIfOffline(
                'clinica.propietario.create',
                offlinePayload,
                {
                    refreshPending,
                    onSuccess: () => undefined,
                    title: t('offline:propietario.queued_title'),
                    description: t('offline:propietario.queued_body'),
                },
            );

            if (queued) {
                onCreated({
                    id: queued.uuid,
                    label: data.razon_social.trim() || data.nombres.trim(),
                    doc: data.numero_documento.trim() || null,
                });
                onSuccess();

                return;
            }

            setJsonSubmitting(true);
            clearErrors();
            try {
                const token =
                    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
                const res = await fetch(jsonStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(data),
                });
                const body = (await res.json()) as {
                    propietario?: PropietarioCreatedPayload;
                    message?: string;
                    errors?: Record<string, string[]>;
                };

                if (res.status === 422 && body.errors) {
                    Object.entries(body.errors).forEach(([key, messages]) => {
                        const msg = messages[0];
                        if (msg) {
                            setError(key as keyof FormData, msg);
                        }
                    });

                    return;
                }

                if (!res.ok || !body.propietario) {
                    return;
                }

                onCreated(body.propietario);
                onSuccess();
            } finally {
                setJsonSubmitting(false);
            }

            return;
        }

        const offlinePayload = {
            ...data,
            tipo_documento: normalizeTipoDocumento(data.tipo_documento) || null,
        };
        const queued = await enqueueIfOffline(
            'clinica.propietario.create',
            offlinePayload,
            {
                refreshPending,
                onSuccess,
                title: t('offline:propietario.queued_title'),
                description: t('offline:propietario.queued_body'),
            },
        );

        if (queued) {
            return;
        }

        post(propietarios.store().url, {
            preserveScroll: true,
            onSuccess,
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={handleClose}
            title={isEdit ? t('form.title_edit') : t('form.title_create')}
            description={t('description')}
            size="lg"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleClose(false)}
                        disabled={submitting}
                        className="cursor-pointer"
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={!canSubmit}
                        className="cursor-pointer gap-2"
                    >
                        {submitting && (
                            <Loader2 className="size-4 animate-spin" aria-hidden />
                        )}
                        {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-5">
                {errors.plan_limit ? (
                    <p
                        className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                        role="alert"
                    >
                        {errors.plan_limit}
                    </p>
                ) : null}
                <FormSection
                    index={0}
                    title={t('form.section_identity')}
                    columns={2}
                    className="gap-4"
                >
                    <FormField
                        id="prop-tipo-doc"
                        label={t('form.tipo_documento')}
                        error={errors.tipo_documento}
                    >
                        <DocumentTypeSelect
                            id="prop-tipo-doc"
                            value={data.tipo_documento}
                            onValueChange={handleTipoDocumentoChange}
                            invalid={Boolean(errors.tipo_documento)}
                        />
                    </FormField>
                    <FormField
                        id="prop-num-doc"
                        label={t('form.numero_documento')}
                        error={errors.numero_documento}
                    >
                        <div
                            className={cn(
                                'flex gap-2',
                                isConsultableDoc ? 'items-stretch' : 'flex-col',
                            )}
                        >
                            <div className="relative min-w-0 flex-1">
                                <Input
                                    id="prop-num-doc"
                                    className={cn(
                                        isConsultableDoc && 'pr-14 tabular-nums tracking-wide',
                                    )}
                                    inputMode={isConsultableDoc ? 'numeric' : undefined}
                                    autoComplete="off"
                                    maxLength={docMaxLen}
                                    value={data.numero_documento}
                                    onChange={(e) =>
                                        setData(
                                            'numero_documento',
                                            isConsultableDoc
                                                ? soloDigitos(e.target.value, docMaxLen)
                                                : e.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(errors.numero_documento)}
                                />
                                {isConsultableDoc && docMaxLen !== undefined ? (
                                    <span
                                        className={cn(
                                            'pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs font-medium tabular-nums',
                                            docCompleto
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-muted-foreground',
                                        )}
                                        aria-hidden
                                    >
                                        {docLen}/{docMaxLen}
                                    </span>
                                ) : null}
                            </div>
                            {isConsultableDoc ? (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    disabled={consultandoDoc || submitting || !docCompleto}
                                    onClick={() => void onConsultarDocumento()}
                                    className={cn(
                                        'size-9 shrink-0 cursor-pointer rounded-lg border-0 shadow-sm transition-all',
                                        'bg-gradient-to-br from-teal-500 to-emerald-600 text-white',
                                        'hover:from-teal-600 hover:to-emerald-700 hover:shadow-md',
                                        'focus-visible:ring-2 focus-visible:ring-emerald-500/40',
                                        'disabled:cursor-not-allowed disabled:from-muted disabled:to-muted disabled:text-muted-foreground disabled:opacity-60 disabled:shadow-none',
                                    )}
                                    aria-label={t('form.consultar_sunat')}
                                    title={t('form.consultar_sunat')}
                                >
                                    {consultandoDoc ? (
                                        <Loader2 className="size-4 animate-spin" aria-hidden />
                                    ) : (
                                        <Search className="size-4" aria-hidden />
                                    )}
                                </Button>
                            ) : null}
                        </div>
                    </FormField>
                    <FormField
                        id="prop-nombres"
                        label={t('form.nombres')}
                        required
                        error={errors.nombres}
                    >
                        <Input
                            id="prop-nombres"
                            value={data.nombres}
                            onChange={(e) => setData('nombres', e.target.value)}
                            autoFocus
                        />
                    </FormField>
                    <FormField
                        id="prop-apellidos"
                        label={t('form.apellidos')}
                        error={errors.apellidos}
                    >
                        <Input
                            id="prop-apellidos"
                            value={data.apellidos}
                            onChange={(e) => setData('apellidos', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        id="prop-razon"
                        label={t('form.razon_social')}
                        error={errors.razon_social}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="prop-razon"
                            value={data.razon_social}
                            onChange={(e) =>
                                setData('razon_social', e.target.value)
                            }
                        />
                    </FormField>
                </FormSection>

                <FormSection
                    index={1}
                    title={t('form.section_contact')}
                    columns={2}
                >
                    <FormField
                        id="prop-email"
                        label={t('form.email')}
                        error={errors.email}
                    >
                        <Input
                            id="prop-email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        id="prop-tel"
                        label={t('form.telefono')}
                        error={errors.telefono}
                    >
                        <Input
                            id="prop-tel"
                            value={data.telefono}
                            onChange={(e) => setData('telefono', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        id="prop-tel2"
                        label={t('form.telefono_alt')}
                        error={errors.telefono_alt}
                    >
                        <Input
                            id="prop-tel2"
                            value={data.telefono_alt}
                            onChange={(e) =>
                                setData('telefono_alt', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="prop-dir"
                        label={t('form.direccion')}
                        error={errors.direccion}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="prop-dir"
                            value={data.direccion}
                            onChange={(e) => setData('direccion', e.target.value)}
                        />
                    </FormField>
                    <div className="sm:col-span-2">
                        <GeoCascadeFields
                            departamentos={departamentos}
                            value={geo}
                            onChange={handleGeoChange}
                            errors={{
                                distrito_id: errors.distrito_id,
                            }}
                        />
                    </div>
                    <FormField
                        id="prop-notas"
                        label={t('form.notas')}
                        error={errors.notas}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="prop-notas"
                            value={data.notas}
                            onChange={(e) => setData('notas', e.target.value)}
                            rows={3}
                        />
                    </FormField>
                    <FormField
                        id="prop-activo"
                        label={t('form.activo')}
                        error={errors.activo}
                    >
                        <div className="flex items-center gap-2 pt-1">
                            <Checkbox
                                id="prop-activo"
                                checked={data.activo}
                                onCheckedChange={(c) =>
                                    setData('activo', c === true)
                                }
                            />
                        </div>
                    </FormField>
                </FormSection>
            </div>
        </FormModal>
    );
}
