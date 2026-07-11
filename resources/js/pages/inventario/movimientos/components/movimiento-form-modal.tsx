import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useMemo, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, SedeFormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { resolveDefaultSedeIdOrEmpty } from '@/lib/default-sede';
import { enqueueIfOffline } from '@/lib/offline/enqueue-if-offline';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import inventario from '@/routes/inventario';
import type { ProductoOptionMovimiento, SedeOptionMovimiento } from '../types';

type MovimientoFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sedeOptions: SedeOptionMovimiento[];
    productoOptions: ProductoOptionMovimiento[];
    defaultSedeId: string;
};

type FormData = {
    producto_id: string | null;
    sede_id: string;
    tipo: string;
    cantidad: string;
    numero_lote: string;
    fecha_vencimiento: string;
    notas: string;
};

const empty: FormData = {
    producto_id: null,
    sede_id: '',
    tipo: 'entrada',
    cantidad: '',
    numero_lote: '',
    fecha_vencimiento: '',
    notas: '',
};

const TIPOS_FORM = ['entrada', 'salida', 'merma'] as const;

export function MovimientoFormModal({
    open,
    onOpenChange,
    sedeOptions,
    productoOptions,
    defaultSedeId,
}: MovimientoFormModalProps) {
    const { t } = useTranslation(['movimientos-inventario', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<FormData>(empty);

    useEffect(() => {
        if (!open) {
            return;
        }
        reset();
        clearErrors();
        setData({
            ...empty,
            sede_id:
                defaultSedeId && defaultSedeId !== ''
                    ? defaultSedeId
                    : resolveDefaultSedeIdOrEmpty(sedeOptions),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, defaultSedeId, sedeOptions]);

    const productoComboboxOptions = useMemo<readonly ComboboxOption[]>(
        () =>
            productoOptions.map((p) => ({
                value: p.id,
                label: p.sku ? `${p.nombre} (${p.sku})` : p.nombre,
            })),
        [productoOptions],
    );

    const sinOpciones = sedeOptions.length === 0 || productoOptions.length === 0;

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (sinOpciones || !data.producto_id) {
            return;
        }

        const onSuccess = () => {
            onOpenChange(false);
            reset();
            clearErrors();
        };

        const payload = {
            producto_id: data.producto_id,
            sede_id: data.sede_id,
            tipo: data.tipo,
            cantidad: data.cantidad,
            numero_lote:
                data.tipo === 'entrada' && data.numero_lote.trim() !== '' ? data.numero_lote.trim() : null,
            fecha_vencimiento:
                data.tipo === 'entrada' && data.fecha_vencimiento.trim() !== ''
                    ? data.fecha_vencimiento.trim()
                    : null,
            notas: data.notas.trim() === '' ? null : data.notas.trim(),
        };

        void (async () => {
            const queued = await enqueueIfOffline('inventario.movimiento.create', payload, {
                refreshPending,
                onSuccess,
                title: t('offline:movimiento.queued_title'),
                description: t('offline:movimiento.queued_body'),
            });

            if (queued) {
                return;
            }

            post(inventario.movimientos.store.url(), {
                preserveScroll: true,
                onSuccess,
            });
        })();
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('modal.title')}
            description={t('modal.description')}
            size="md"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" disabled={processing} onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || sinOpciones} className="gap-2">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        {t('modal.submit')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <SedeFormField
                    id="mov-sede"
                    label={t('modal.sede')}
                    sedes={sedeOptions}
                    value={data.sede_id || null}
                    onChange={(sedeId) => setData('sede_id', sedeId ?? '')}
                    error={errors.sede_id}
                    required
                    disabled={processing || sinOpciones}
                    allowNone={false}
                    noneLabel={t('filter_sede_placeholder')}
                    controlClassName="h-10 w-full min-w-0 cursor-pointer"
                    formatLabel={(s) =>
                        s.codigo ? `${s.nombre} · ${s.codigo}` : s.nombre
                    }
                />

                <FormField
                    id="mov-producto"
                    label={t('modal.producto')}
                    error={errors.producto_id}
                    required
                    className="min-w-0"
                >
                    <Combobox
                        id="mov-producto"
                        options={productoComboboxOptions}
                        value={data.producto_id}
                        onChange={(v) => setData('producto_id', v)}
                        placeholder={t('modal.producto_placeholder')}
                        searchPlaceholder={t('modal.producto_search')}
                        emptyMessage={t('modal.producto_empty')}
                        disabled={processing || sinOpciones}
                        clearable={false}
                        aria-invalid={Boolean(errors.producto_id)}
                    />
                </FormField>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="mov-tipo" label={t('modal.tipo')} error={errors.tipo} required className="min-w-0">
                        <Select value={data.tipo} onValueChange={(v) => setData('tipo', v)} disabled={processing || sinOpciones}>
                            <SelectTrigger id="mov-tipo" className="h-10 w-full min-w-0 cursor-pointer">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {TIPOS_FORM.map((tp) => (
                                    <SelectItem key={tp} value={tp}>
                                        {t(`tipos.${tp}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField id="mov-cantidad" label={t('modal.cantidad')} error={errors.cantidad} required hint={t('modal.cantidad_hint')} className="min-w-0">
                        <Input
                            id="mov-cantidad"
                            type="text"
                            inputMode="decimal"
                            autoComplete="off"
                            value={data.cantidad}
                            onChange={(e) => setData('cantidad', e.target.value)}
                            disabled={processing || sinOpciones}
                            className="h-10 w-full"
                            aria-invalid={Boolean(errors.cantidad)}
                        />
                    </FormField>
                </div>

                {data.tipo === 'entrada' ? (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="mov-lote"
                            label={t('modal.lote')}
                            error={errors.numero_lote}
                            hint={t('modal.lote_hint')}
                            className="min-w-0"
                        >
                            <Input
                                id="mov-lote"
                                value={data.numero_lote}
                                onChange={(e) => setData('numero_lote', e.target.value)}
                                disabled={processing || sinOpciones}
                                maxLength={128}
                                className="h-10 w-full"
                                placeholder={t('modal.lote_placeholder')}
                            />
                        </FormField>
                        <FormField
                            id="mov-venc"
                            label={t('modal.vencimiento')}
                            error={errors.fecha_vencimiento}
                            className="min-w-0"
                        >
                            <Input
                                id="mov-venc"
                                type="date"
                                value={data.fecha_vencimiento}
                                onChange={(e) => setData('fecha_vencimiento', e.target.value)}
                                disabled={processing || sinOpciones}
                                className="h-10 w-full"
                            />
                        </FormField>
                    </div>
                ) : (
                    <p className="text-xs leading-relaxed text-muted-foreground">{t('modal.salida_fefo_hint')}</p>
                )}

                <FormField id="mov-notas" label={t('modal.notas')} error={errors.notas} className="min-w-0">
                    <Textarea
                        id="mov-notas"
                        rows={3}
                        value={data.notas}
                        onChange={(e) => setData('notas', e.target.value)}
                        disabled={processing || sinOpciones}
                        className="min-h-20 w-full resize-y text-sm"
                    />
                </FormField>
            </div>
        </FormModal>
    );
}
