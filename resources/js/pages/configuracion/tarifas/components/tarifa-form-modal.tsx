import { router, useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import type { CatalogoGrupo, GroomingTarifa, HotelTarifa } from '../types';

type TarifaKind = 'grooming' | 'hotel';

type TarifaFormModalProps = {
    kind: TarifaKind;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tarifa: GroomingTarifa | HotelTarifa | null;
    catalogo: CatalogoGrupo[];
};

type FormData = {
    servicio: string;
    tipo_estancia: string;
    precio_lista: string;
    moneda: string;
    activo: boolean;
};

const empty: FormData = {
    servicio: '',
    tipo_estancia: '',
    precio_lista: '',
    moneda: 'PEN',
    activo: true,
};

const MONEDA_OPTIONS: ComboboxOption[] = [
    { value: 'PEN', label: 'PEN — Soles' },
    { value: 'USD', label: 'USD — Dólares' },
];

export function TarifaFormModal({ kind, open, onOpenChange, tarifa, catalogo }: TarifaFormModalProps) {
    const { t } = useTranslation(['tarifas-servicios', 'grooming', 'hotel', 'common']);
    const isEdit = tarifa !== null;
    const isGrooming = kind === 'grooming';

    const { data, setData, processing, errors, reset, clearErrors } = useForm<FormData>(empty);

    const labelForSlug = (slug: string) => {
        if (isGrooming) {
            return t(`grooming:tipos_servicio.${slug}`, { defaultValue: slug });
        }

        return t(`hotel:tipos_estancia.${slug}`, { defaultValue: slug });
    };

    const catalogoOptions = useMemo<ComboboxOption[]>(() => {
        const options: ComboboxOption[] = [];

        for (const grupo of catalogo) {
            const groupLabel = isGrooming
                ? t(`grooming:grupos.${grupo.grupo}`, { defaultValue: grupo.grupo })
                : t(`hotel:grupos.${grupo.grupo}`, { defaultValue: grupo.grupo });

            for (const slug of grupo.items) {
                options.push({
                    value: slug,
                    label: `${groupLabel} · ${labelForSlug(slug)}`,
                });
            }
        }

        return options;
    }, [catalogo, isGrooming, t]);

    useEffect(() => {
        if (!open) {
            return;
        }
        if (!tarifa) {
            reset();
            clearErrors();
            return;
        }
        if (isGrooming && 'servicio' in tarifa) {
            setData({
                servicio: tarifa.servicio,
                tipo_estancia: '',
                precio_lista: String(tarifa.precio_lista),
                moneda: tarifa.moneda,
                activo: tarifa.activo,
            });
        } else if (!isGrooming && 'tipo_estancia' in tarifa) {
            setData({
                servicio: '',
                tipo_estancia: tarifa.tipo_estancia,
                precio_lista: String(tarifa.precio_lista),
                moneda: tarifa.moneda,
                activo: tarifa.activo,
            });
        }
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, tarifa?.id, kind]);

    const codigoValue = isGrooming ? data.servicio : data.tipo_estancia;
    const codigoField = isGrooming ? 'servicio' : 'tipo_estancia';

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const onSuccess = () => onOpenChange(false);

        const payload = isGrooming
            ? {
                  servicio: data.servicio,
                  precio_lista: data.precio_lista,
                  moneda: data.moneda,
                  activo: data.activo,
              }
            : {
                  tipo_estancia: data.tipo_estancia,
                  precio_lista: data.precio_lista,
                  moneda: data.moneda,
                  activo: data.activo,
              };

        const opts = { preserveScroll: true, onSuccess };

        if (isEdit && tarifa) {
            const url = isGrooming
                ? `/configuracion/tarifas/grooming/${tarifa.id}`
                : `/configuracion/tarifas/hotel/${tarifa.id}`;
            router.put(url, payload, opts);
            return;
        }

        const url = isGrooming ? '/configuracion/tarifas/grooming' : '/configuracion/tarifas/hotel';
        router.post(url, payload, opts);
    };

    const canSubmit =
        codigoValue.trim() !== '' &&
        data.precio_lista.trim() !== '' &&
        !Number.isNaN(Number(data.precio_lista));

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={
                isEdit
                    ? t('form.title_edit')
                    : isGrooming
                      ? t('form.title_create_grooming')
                      : t('form.title_create_hotel')
            }
            description={isGrooming ? t('form.description_grooming') : t('form.description_hotel')}
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" disabled={processing} onClick={() => onOpenChange(false)}>
                        {t('form.cancelar')}
                    </Button>
                    <Button type="submit" disabled={processing || !canSubmit} className="gap-2">
                        {processing ? <Loader2 className="size-4 animate-spin" /> : null}
                        {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-5">
                <FormField
                    id="tarifa-codigo"
                    label={isGrooming ? t('form.servicio') : t('form.tipo_estancia')}
                    error={errors[codigoField]}
                    required
                    hint={isEdit ? t('form.codigo_locked') : undefined}
                >
                    <Combobox
                        id="tarifa-codigo"
                        options={catalogoOptions}
                        value={codigoValue || null}
                        onChange={(value) => setData(codigoField, value ?? '')}
                        placeholder={t('form.codigo_placeholder')}
                        searchPlaceholder={t('form.codigo_search')}
                        emptyMessage={t('form.codigo_empty')}
                        disabled={isEdit}
                        clearable={!isEdit}
                        aria-invalid={Boolean(errors[codigoField])}
                    />
                </FormField>

                <div className="grid gap-5 sm:grid-cols-2">
                    <FormField
                        id="tarifa-precio"
                        label={t('form.precio_lista')}
                        error={errors.precio_lista}
                        required
                    >
                        <Input
                            id="tarifa-precio"
                            type="number"
                            min={0}
                            step="0.01"
                            inputMode="decimal"
                            value={data.precio_lista}
                            onChange={(e) => setData('precio_lista', e.target.value)}
                            className="tabular-nums"
                            aria-invalid={Boolean(errors.precio_lista)}
                        />
                    </FormField>

                    <FormField id="tarifa-moneda" label={t('form.moneda')} error={errors.moneda} required>
                        <Combobox
                            id="tarifa-moneda"
                            options={MONEDA_OPTIONS}
                            value={data.moneda || null}
                            onChange={(value) => setData('moneda', value ?? 'PEN')}
                            placeholder={t('form.moneda_placeholder')}
                            clearable={false}
                            aria-invalid={Boolean(errors.moneda)}
                        />
                    </FormField>
                </div>

                <FormField id="tarifa-activo" label={t('form.estado')}>
                    <label
                        htmlFor="tarifa-activo"
                        className="flex cursor-pointer items-center gap-3 rounded-lg border border-border/60 bg-muted/25 px-4 py-3 text-sm transition-colors hover:bg-muted/40"
                    >
                        <Checkbox
                            id="tarifa-activo"
                            checked={data.activo}
                            onCheckedChange={(checked) => setData('activo', checked === true)}
                        />
                        <span className="leading-snug">{t('form.activo')}</span>
                    </label>
                </FormField>
            </div>
        </FormModal>
    );
}
