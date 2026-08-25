import { useForm } from '@inertiajs/react';
import { Loader2, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, FormSection } from '@/components/forms';
import {
    GeoCascadeFields,
    type GeoCascadeValue,
} from '@/components/geo/geo-cascade-fields';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toastManager } from '@/lib/toast';
import { cn } from '@/lib/utils';
import type { ClinicaAsesorada, GeoOption } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    clinica: ClinicaAsesorada | null;
    departamentos: readonly GeoOption[];
};

type FormData = {
    nombre: string;
    ruc: string;
    direccion: string;
    distrito_id: number | null;
    activo: boolean;
};

const emptyForm: FormData = {
    nombre: '',
    ruc: '',
    direccion: '',
    distrito_id: null,
    activo: true,
};

const RUC_MAX_LEN = 11;
const CONSULTA_RUC_URL = '/clinica/clinicas-asesoradas/consulta-ruc';

function soloDigitosRuc(value: string): string {
    return value.replace(/\D/g, '').slice(0, RUC_MAX_LEN);
}

function initialGeo(clinica: ClinicaAsesorada | null): GeoCascadeValue {
    if (!clinica?.distrito_id) {
        return { departamento_id: null, provincia_id: null, distrito_id: null };
    }

    return {
        departamento_id: null,
        provincia_id: null,
        distrito_id: clinica.distrito_id,
    };
}

export function ClinicaAsesoradaFormModal({
    open,
    onOpenChange,
    clinica,
    departamentos,
}: Props) {
    const { t } = useTranslation(['clinicas-asesoradas', 'common']);
    const isEdit = clinica !== null;
    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } =
        useForm<FormData>(emptyForm);
    const [geo, setGeo] = useState<GeoCascadeValue>(initialGeo(null));
    const [consultandoRuc, setConsultandoRuc] = useState(false);
    const lastConsultaRucRef = useRef<string | null>(null);

    const rucLen = soloDigitosRuc(data.ruc).length;
    const rucCompleto = rucLen === RUC_MAX_LEN;

    useEffect(() => {
        transform((form) => ({
            ...form,
            ruc: soloDigitosRuc(form.ruc),
        }));
    }, [transform]);

    useEffect(() => {
        if (!open) {
            return;
        }
        clearErrors();
        setConsultandoRuc(false);

        if (clinica) {
            const ruc = soloDigitosRuc(clinica.ruc ?? '');
            setData({
                nombre: clinica.nombre,
                ruc,
                direccion: clinica.direccion ?? '',
                distrito_id: clinica.distrito_id,
                activo: clinica.activo,
            });
            setGeo(initialGeo(clinica));
            lastConsultaRucRef.current =
                ruc.length === RUC_MAX_LEN ? ruc : null;
        } else {
            reset();
            setData(emptyForm);
            setGeo(initialGeo(null));
            lastConsultaRucRef.current = null;
        }
    }, [open, clinica, clearErrors, reset, setData]);

    const onConsultarRuc = async (forcedRuc?: string) => {
        const ruc = soloDigitosRuc(forcedRuc ?? data.ruc);

        if (ruc.length !== RUC_MAX_LEN) {
            toastManager.error({ title: t('form.consultar_invalid') });

            return;
        }

        lastConsultaRucRef.current = ruc;
        setConsultandoRuc(true);

        try {
            const url = `${CONSULTA_RUC_URL}?ruc=${encodeURIComponent(ruc)}`;
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
                    ruc: string;
                    razon_social: string;
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
            setData((prev) => ({
                ...prev,
                ruc: d.ruc ?? ruc,
                nombre:
                    typeof d.razon_social === 'string' && d.razon_social !== ''
                        ? d.razon_social
                        : prev.nombre,
                direccion:
                    typeof d.direccion === 'string' && d.direccion !== ''
                        ? d.direccion
                        : prev.direccion,
            }));
        } catch {
            toastManager.error({ title: t('form.consultar_error') });
        } finally {
            setConsultandoRuc(false);
        }
    };

    useEffect(() => {
        if (!open || !rucCompleto || consultandoRuc || processing) {
            return;
        }

        const ruc = soloDigitosRuc(data.ruc);

        if (lastConsultaRucRef.current === ruc) {
            return;
        }

        void onConsultarRuc(ruc);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, data.ruc, rucCompleto, consultandoRuc, processing]);

    const canSave = useMemo(() => data.nombre.trim() !== '', [data.nombre]);

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        if (!canSave) {
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEdit && clinica) {
            put(`/clinica/clinicas-asesoradas/${clinica.id}`, options);
            return;
        }

        post('/clinica/clinicas-asesoradas', {
            ...options,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={
                isEdit
                    ? t('form.edit_title')
                    : t('form.create_title')
            }
            description={t('form.description')}
            size="lg"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || !canSave}>
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : null}
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <FormSection index={0} title="" columns={2} className="gap-0">
                <FormField
                    label={t('form.ruc')}
                    error={errors.ruc}
                    className="sm:col-span-2"
                >
                    <div className="flex items-stretch gap-2">
                        <div className="relative min-w-0 flex-1">
                            <Input
                                className="pr-14 font-mono tabular-nums tracking-wide"
                                inputMode="numeric"
                                autoComplete="off"
                                maxLength={RUC_MAX_LEN}
                                value={data.ruc}
                                onChange={(e) =>
                                    setData(
                                        'ruc',
                                        soloDigitosRuc(e.target.value),
                                    )
                                }
                                aria-invalid={Boolean(errors.ruc)}
                                placeholder={t('form.ruc_placeholder')}
                            />
                            <span
                                className={cn(
                                    'pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs font-medium tabular-nums',
                                    rucCompleto
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-muted-foreground',
                                )}
                                aria-hidden
                            >
                                {rucLen}/{RUC_MAX_LEN}
                            </span>
                        </div>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className={cn(
                                'size-9 shrink-0 cursor-pointer rounded-lg border-0 shadow-sm transition-all',
                                'bg-gradient-to-br from-teal-500 to-emerald-600 text-white',
                                'hover:from-teal-600 hover:to-emerald-700 hover:shadow-md',
                                'focus-visible:ring-2 focus-visible:ring-emerald-500/40',
                                'disabled:cursor-not-allowed disabled:from-muted disabled:to-muted disabled:text-muted-foreground disabled:opacity-60 disabled:shadow-none',
                            )}
                            disabled={
                                consultandoRuc || processing || !rucCompleto
                            }
                            onClick={() => void onConsultarRuc()}
                            aria-label={t('form.consultar_ruc')}
                            title={t('form.consultar_ruc')}
                        >
                            {consultandoRuc ? (
                                <Loader2
                                    className="size-4 animate-spin"
                                    aria-hidden
                                />
                            ) : (
                                <Search className="size-4" aria-hidden />
                            )}
                        </Button>
                    </div>
                    <p className="mt-1.5 text-xs text-muted-foreground">
                        {t('form.ruc_hint')}
                    </p>
                </FormField>
                <FormField
                    label={t('form.nombre')}
                    error={errors.nombre}
                    required
                    className="sm:col-span-2"
                >
                    <Input
                        value={data.nombre}
                        onChange={(e) => setData('nombre', e.target.value)}
                        maxLength={200}
                    />
                </FormField>
                <FormField
                    label={t('form.direccion')}
                    error={errors.direccion}
                    className="sm:col-span-2"
                >
                    <Input
                        value={data.direccion}
                        onChange={(e) => setData('direccion', e.target.value)}
                        maxLength={255}
                    />
                </FormField>
                <div className="sm:col-span-2">
                    <GeoCascadeFields
                        value={geo}
                        onChange={(next) => {
                            setGeo(next);
                            setData('distrito_id', next.distrito_id);
                        }}
                        departamentos={departamentos}
                        errors={{ distrito_id: errors.distrito_id }}
                        labels={{
                            departamento: t('form.departamento'),
                            provincia: t('form.provincia'),
                            distrito: t('form.distrito'),
                        }}
                    />
                </div>
                <div className="flex items-center gap-2 sm:col-span-2">
                    <Checkbox
                        id="clinica-asesorada-activa"
                        checked={data.activo}
                        onCheckedChange={(checked) =>
                            setData('activo', checked === true)
                        }
                    />
                    <Label htmlFor="clinica-asesorada-activa">
                        {t('form.activo')}
                    </Label>
                </div>
            </FormSection>
        </FormModal>
    );
}
