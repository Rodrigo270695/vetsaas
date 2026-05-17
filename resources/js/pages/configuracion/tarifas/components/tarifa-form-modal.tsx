import { router, useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

export function TarifaFormModal({ kind, open, onOpenChange, tarifa, catalogo }: TarifaFormModalProps) {
    const { t } = useTranslation(['tarifas-servicios', 'grooming', 'hotel', 'common']);
    const isEdit = tarifa !== null;
    const isGrooming = kind === 'grooming';

    const { data, setData, processing, errors, reset, clearErrors } = useForm<FormData>(empty);

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

    const labelForSlug = (slug: string) => {
        if (isGrooming) {
            return t(`grooming:tipos_servicio.${slug}`, { defaultValue: slug });
        }

        return t(`hotel:tipos_estancia.${slug}`, { defaultValue: slug });
    };

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

    const codigoValue = isGrooming ? data.servicio : data.tipo_estancia;
    const codigoField = isGrooming ? 'servicio' : 'tipo_estancia';

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={
                isEdit
                    ? t('actions.editar')
                    : isGrooming
                      ? t('actions.nueva_grooming')
                      : t('actions.nueva_hotel')
            }
        >
            <form onSubmit={onSubmit} className="flex flex-col gap-4">
                <FormField
                    label={isGrooming ? t('form.servicio') : t('form.tipo_estancia')}
                    error={errors[codigoField]}
                >
                    <Select
                        value={codigoValue || undefined}
                        onValueChange={(v) => setData(codigoField, v)}
                        disabled={isEdit}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder={t('common:select.placeholder')} />
                        </SelectTrigger>
                        <SelectContent>
                            {catalogo.map((grupo) => (
                                <SelectGroup key={grupo.grupo}>
                                    <SelectLabel>
                                        {isGrooming
                                            ? t(`grooming:grupos.${grupo.grupo}`, { defaultValue: grupo.grupo })
                                            : t(`hotel:grupos.${grupo.grupo}`, { defaultValue: grupo.grupo })}
                                    </SelectLabel>
                                    {grupo.items.map((slug) => (
                                        <SelectItem key={slug} value={slug}>
                                            {labelForSlug(slug)}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            ))}
                        </SelectContent>
                    </Select>
                </FormField>

                <FormField label={t('form.precio_lista')} error={errors.precio_lista}>
                    <Input
                        type="number"
                        min={0}
                        step="0.01"
                        value={data.precio_lista}
                        onChange={(e) => setData('precio_lista', e.target.value)}
                    />
                </FormField>

                <FormField label={t('form.moneda')} error={errors.moneda}>
                    <Select value={data.moneda} onValueChange={(v) => setData('moneda', v)}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="PEN">PEN</SelectItem>
                            <SelectItem value="USD">USD</SelectItem>
                        </SelectContent>
                    </Select>
                </FormField>

                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={data.activo}
                        onCheckedChange={(c) => setData('activo', c === true)}
                    />
                    {t('form.activo')}
                </label>

                <div className="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        {t('form.cancelar')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? <Loader2 className="size-4 animate-spin" aria-hidden /> : null}
                        {t('form.guardar')}
                    </Button>
                </div>
            </form>
        </FormModal>
    );
}
