import { useForm } from '@inertiajs/react';
import { Loader2, Plus, Trash2 } from 'lucide-react';
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
import { enqueueIfOffline, isOfflineMode } from '@/lib/offline/enqueue-if-offline';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import { toastManager } from '@/lib/toast';
import inventario from '@/routes/inventario';
import type { ProductoOptionCompra, ProveedorOptionCompra, SedeOptionCompra } from '../types';

type CompraFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sedeOptions: SedeOptionCompra[];
    proveedorOptions: ProveedorOptionCompra[];
    productoOptions: ProductoOptionCompra[];
    defaultSedeId: string;
};

type LineaForm = {
    producto_id: string | null;
    cantidad: string;
    costo_unitario: string;
};

type FormData = {
    sede_id: string;
    proveedor_id: string;
    fecha_documento: string;
    numero_documento: string;
    serie: string;
    moneda: string;
    total: string;
    notas: string;
    factura: File | null;
    lineas: LineaForm[];
};

const emptyLinea = (): LineaForm => ({
    producto_id: null,
    cantidad: '1',
    costo_unitario: '',
});

const emptyForm = (): FormData => ({
    sede_id: '',
    proveedor_id: '',
    fecha_documento: new Date().toISOString().slice(0, 10),
    numero_documento: '',
    serie: '',
    moneda: 'PEN',
    total: '',
    notas: '',
    factura: null,
    lineas: [emptyLinea()],
});

export function CompraFormModal({
    open,
    onOpenChange,
    sedeOptions,
    proveedorOptions,
    productoOptions,
    defaultSedeId,
}: CompraFormModalProps) {
    const { t } = useTranslation(['compras-inventario', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<FormData>(emptyForm());

    useEffect(() => {
        if (!open) {
            return;
        }
        reset();
        clearErrors();
        setData({
            ...emptyForm(),
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

    const updateLinea = (index: number, patch: Partial<LineaForm>) => {
        setData(
            'lineas',
            data.lineas.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );
    };

    const addLinea = () => {
        setData('lineas', [...data.lineas, emptyLinea()]);
    };

    const removeLinea = (index: number) => {
        if (data.lineas.length <= 1) {
            return;
        }
        setData(
            'lineas',
            data.lineas.filter((_, i) => i !== index),
        );
    };

    const fieldErr = (key: string) => (errors as Record<string, string | undefined>)[key];

    const buildCreatePayload = (raw: FormData): Record<string, unknown> => ({
        sede_id: raw.sede_id,
        proveedor_id: raw.proveedor_id.trim() === '' ? null : raw.proveedor_id.trim(),
        fecha_documento: raw.fecha_documento,
        numero_documento: raw.numero_documento.trim() === '' ? null : raw.numero_documento.trim(),
        serie: raw.serie.trim() === '' ? null : raw.serie.trim(),
        moneda: raw.moneda.trim() === '' ? 'PEN' : raw.moneda.trim(),
        total: raw.total.trim() === '' ? null : raw.total.trim(),
        notas: raw.notas.trim() === '' ? null : raw.notas.trim(),
        lineas: raw.lineas
            .filter((l) => l.producto_id)
            .map((l) => ({
                producto_id: l.producto_id,
                cantidad: l.cantidad,
                costo_unitario: l.costo_unitario.trim() === '' ? null : l.costo_unitario.trim(),
            })),
    });

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (sinOpciones) {
            return;
        }

        const onSuccess = () => {
            onOpenChange(false);
            reset();
            clearErrors();
        };

        if (isOfflineMode()) {
            if (data.factura instanceof File) {
                toastManager.warning({
                    title: t('offline:compra.factura_requires_online'),
                });

                return;
            }

            void (async () => {
                const queued = await enqueueIfOffline(
                    'inventario.compra.create',
                    buildCreatePayload(data),
                    {
                        refreshPending,
                        onSuccess,
                        title: t('offline:compra.queued_title'),
                        description: t('offline:compra.queued_body'),
                    },
                );

                if (queued) {
                    return;
                }

                post(inventario.compras.store.url(), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess,
                });
            })();

            return;
        }

        post(inventario.compras.store.url(), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess,
        });
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('modal.title')}
            description={t('modal.description')}
            size="xl"
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
            <div className="grid max-h-[min(70vh,720px)] gap-4 overflow-y-auto pr-1">
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="compra-fecha" label={t('modal.fecha_documento')} error={errors.fecha_documento} required className="sm:col-span-2">
                        <Input
                            id="compra-fecha"
                            type="date"
                            value={data.fecha_documento}
                            onChange={(e) => setData('fecha_documento', e.target.value)}
                            disabled={processing}
                            className="h-10 max-w-xs"
                        />
                    </FormField>

                    <SedeFormField
                        id="compra-sede"
                        label={t('modal.sede')}
                        sedes={sedeOptions}
                        value={data.sede_id || null}
                        onChange={(sedeId) => setData('sede_id', sedeId ?? '')}
                        error={errors.sede_id}
                        required
                        disabled={processing || sinOpciones}
                        allowNone={false}
                        noneLabel={t('modal.sede_placeholder')}
                        controlClassName="h-10 w-full min-w-0 cursor-pointer"
                        formatLabel={(s) =>
                            s.codigo ? `${s.nombre} · ${s.codigo}` : s.nombre
                        }
                    />

                    <FormField id="compra-proveedor" label={t('modal.proveedor')} error={errors.proveedor_id} className="min-w-0">
                        <Select
                            value={data.proveedor_id === '' ? 'none' : data.proveedor_id}
                            onValueChange={(v) => setData('proveedor_id', v === 'none' ? '' : v)}
                            disabled={processing || sinOpciones}
                        >
                            <SelectTrigger id="compra-proveedor" className="h-10 w-full min-w-0 cursor-pointer">
                                <SelectValue placeholder={t('modal.proveedor_placeholder')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">{t('modal.proveedor_none')}</SelectItem>
                                {proveedorOptions.map((p) => (
                                    <SelectItem key={p.id} value={p.id}>
                                        <span className="truncate">
                                            <span className="font-mono text-xs text-muted-foreground">{p.ruc}</span>
                                            <span className="ml-2">{p.razon_social}</span>
                                        </span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <div className="grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-2">
                        <FormField id="compra-serie" label={t('modal.serie')} error={errors.serie}>
                            <Input
                                id="compra-serie"
                                value={data.serie}
                                onChange={(e) => setData('serie', e.target.value)}
                                disabled={processing}
                                maxLength={16}
                                className="h-10"
                            />
                        </FormField>
                        <FormField id="compra-numero" label={t('modal.numero_documento')} error={errors.numero_documento}>
                            <Input
                                id="compra-numero"
                                value={data.numero_documento}
                                onChange={(e) => setData('numero_documento', e.target.value)}
                                disabled={processing}
                                maxLength={64}
                                className="h-10"
                            />
                        </FormField>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-2">
                        <FormField id="compra-moneda" label={t('modal.moneda')} error={errors.moneda}>
                            <Input
                                id="compra-moneda"
                                value={data.moneda}
                                onChange={(e) => setData('moneda', e.target.value.toUpperCase().slice(0, 3))}
                                disabled={processing}
                                maxLength={3}
                                className="h-10 font-mono uppercase"
                            />
                        </FormField>

                        <FormField id="compra-total" label={t('modal.total')} error={errors.total}>
                            <Input
                                id="compra-total"
                                type="number"
                                inputMode="decimal"
                                min={0}
                                step="0.01"
                                value={data.total}
                                onChange={(e) => setData('total', e.target.value)}
                                disabled={processing}
                                className="h-10"
                                placeholder={t('modal.total_placeholder')}
                            />
                        </FormField>
                    </div>
                </div>

                <FormField id="compra-factura" label={t('modal.factura')} error={errors.factura} className="min-w-0">
                    <Input
                        id="compra-factura"
                        type="file"
                        accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"
                        disabled={processing}
                        className="h-10 cursor-pointer pt-1.5 file:mr-3 file:cursor-pointer"
                        onChange={(e) => {
                            const f = e.target.files?.[0] ?? null;
                            setData('factura', f);
                        }}
                    />
                    <p className="text-xs text-muted-foreground">{t('modal.factura_help')}</p>
                </FormField>

                <FormField id="compra-notas" label={t('modal.notas')} error={errors.notas} className="min-w-0">
                    <Textarea
                        id="compra-notas"
                        value={data.notas}
                        onChange={(e) => setData('notas', e.target.value)}
                        disabled={processing}
                        rows={2}
                        className="min-h-16 resize-y"
                    />
                </FormField>

                <div className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="text-sm font-medium text-foreground">{t('modal.lineas_title')}</span>
                        <Button type="button" variant="outline" size="sm" className="gap-1" onClick={addLinea} disabled={processing}>
                            <Plus className="size-4" />
                            {t('modal.linea_add')}
                        </Button>
                    </div>

                    {fieldErr('lineas') ? <p className="text-sm text-destructive">{fieldErr('lineas')}</p> : null}

                    <div className="space-y-4 rounded-lg border border-border p-3">
                        {data.lineas.map((linea, index) => (
                            <div
                                key={index}
                                className="grid gap-3 border-b border-border pb-4 last:border-b-0 last:pb-0 sm:grid-cols-[1fr_7rem_7rem_auto]"
                            >
                                <FormField
                                    id={`compra-linea-p-${index}`}
                                    label={t('modal.linea_producto')}
                                    error={fieldErr(`lineas.${index}.producto_id`)}
                                    required
                                    className="min-w-0"
                                >
                                    <Combobox
                                        id={`compra-linea-p-${index}`}
                                        options={productoComboboxOptions}
                                        value={linea.producto_id}
                                        onChange={(v) => updateLinea(index, { producto_id: v })}
                                        placeholder={t('modal.linea_producto_placeholder')}
                                        searchPlaceholder={t('modal.linea_producto_search')}
                                        disabled={processing}
                                    />
                                </FormField>
                                <FormField
                                    id={`compra-linea-q-${index}`}
                                    label={t('modal.linea_cantidad')}
                                    error={fieldErr(`lineas.${index}.cantidad`)}
                                    required
                                >
                                    <Input
                                        id={`compra-linea-q-${index}`}
                                        type="number"
                                        inputMode="decimal"
                                        min={0.001}
                                        step="any"
                                        value={linea.cantidad}
                                        onChange={(e) => updateLinea(index, { cantidad: e.target.value })}
                                        disabled={processing}
                                        className="h-10"
                                    />
                                </FormField>
                                <FormField
                                    id={`compra-linea-c-${index}`}
                                    label={t('modal.linea_costo')}
                                    error={fieldErr(`lineas.${index}.costo_unitario`)}
                                >
                                    <Input
                                        id={`compra-linea-c-${index}`}
                                        type="number"
                                        inputMode="decimal"
                                        min={0}
                                        step="any"
                                        value={linea.costo_unitario}
                                        onChange={(e) => updateLinea(index, { costo_unitario: e.target.value })}
                                        disabled={processing}
                                        className="h-10"
                                        placeholder="—"
                                    />
                                </FormField>
                                <div className="flex items-end justify-end sm:col-span-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="text-muted-foreground hover:text-destructive"
                                        disabled={processing || data.lineas.length <= 1}
                                        onClick={() => removeLinea(index)}
                                        aria-label={t('modal.linea_remove')}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </FormModal>
    );
}
