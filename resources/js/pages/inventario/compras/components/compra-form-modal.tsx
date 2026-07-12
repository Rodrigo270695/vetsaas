import { useForm } from '@inertiajs/react';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal, SedeFormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
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
import type {
    ProductoOptionCompra,
    ProductoUnidadOptionCompra,
    ProveedorOptionCompra,
    SedeOptionCompra,
} from '../types';
import { ProductoCompraCombobox } from './producto-compra-combobox';
import { ProductoQuickCreateDialog } from './producto-quick-create-dialog';

type QuickCreateProductoState = {
    lineIndex: number;
    nombre: string;
    precioCompra?: string;
};

type CompraFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sedeOptions: SedeOptionCompra[];
    proveedorOptions: ProveedorOptionCompra[];
    productoOptions: ProductoOptionCompra[];
    unidadOptions: ProductoUnidadOptionCompra[];
    canCreateProducto: boolean;
    defaultSedeId: string;
};

type LineaForm = {
    producto_id: string | null;
    cantidad: string;
    costo_unitario: string;
    numero_lote: string;
    fecha_vencimiento: string;
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
    numero_lote: '',
    fecha_vencimiento: '',
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
    unidadOptions,
    canCreateProducto,
    defaultSedeId,
}: CompraFormModalProps) {
    const { t } = useTranslation(['compras-inventario', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<FormData>(emptyForm());
    const [productosLocal, setProductosLocal] = useState<ProductoOptionCompra[]>([]);
    const [quickCreateProducto, setQuickCreateProducto] = useState<QuickCreateProductoState | null>(null);

    useEffect(() => {
        if (!open) {
            setQuickCreateProducto(null);
            return;
        }
        reset();
        clearErrors();
        setProductosLocal(productoOptions);
        setData({
            ...emptyForm(),
            sede_id:
                defaultSedeId && defaultSedeId !== ''
                    ? defaultSedeId
                    : resolveDefaultSedeIdOrEmpty(sedeOptions),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, defaultSedeId, sedeOptions, productoOptions]);

    const sinSedes = sedeOptions.length === 0;

    const appendProducto = (producto: ProductoOptionCompra) => {
        setProductosLocal((prev) => {
            if (prev.some((p) => p.id === producto.id)) {
                return prev;
            }

            return [...prev, producto].sort((a, b) => a.nombre.localeCompare(b.nombre));
        });
    };

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
                numero_lote: l.numero_lote.trim() === '' ? null : l.numero_lote.trim(),
                fecha_vencimiento: l.fecha_vencimiento.trim() === '' ? null : l.fecha_vencimiento.trim(),
            })),
    });

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (sinSedes) {
            return;
        }

        const onSuccess = () => {
            onOpenChange(false);
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

    const handleCompraOpenChange = (nextOpen: boolean) => {
        if (!nextOpen && quickCreateProducto !== null) {
            return;
        }

        onOpenChange(nextOpen);
    };

    return (
        <>
        <FormModal
            open={open}
            onOpenChange={handleCompraOpenChange}
            title={t('modal.title')}
            description={t('modal.description')}
            size="xl"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => {
                            setQuickCreateProducto(null);
                            onOpenChange(false);
                        }}
                    >
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || sinSedes} className="gap-2">
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
                        value={data.sede_id}
                        onChange={(sedeId) => setData('sede_id', sedeId ?? '')}
                        error={errors.sede_id}
                        required
                        disabled={processing || sinSedes}
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
                            disabled={processing || sinSedes}
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
                                className="grid gap-3 border-b border-border pb-4 last:border-b-0 last:pb-0 sm:grid-cols-2 lg:grid-cols-[1fr_6rem_6rem_8rem_9rem_auto]"
                            >
                                <FormField
                                    id={`compra-linea-p-${index}`}
                                    label={t('modal.linea_producto')}
                                    error={fieldErr(`lineas.${index}.producto_id`)}
                                    required
                                    className="min-w-0"
                                >
                                    <ProductoCompraCombobox
                                        id={`compra-linea-p-${index}`}
                                        value={linea.producto_id}
                                        onChange={(v) => updateLinea(index, { producto_id: v })}
                                        productoOptions={productosLocal}
                                        canCreateProducto={canCreateProducto}
                                        onRequestCreate={(nombre) =>
                                            setQuickCreateProducto({
                                                lineIndex: index,
                                                nombre,
                                                precioCompra: linea.costo_unitario,
                                            })
                                        }
                                        disabled={processing}
                                        aria-invalid={Boolean(fieldErr(`lineas.${index}.producto_id`))}
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
                                <FormField
                                    id={`compra-linea-lote-${index}`}
                                    label={t('modal.linea_lote')}
                                    error={fieldErr(`lineas.${index}.numero_lote`)}
                                    className="min-w-0 sm:col-span-1"
                                >
                                    <Input
                                        id={`compra-linea-lote-${index}`}
                                        value={linea.numero_lote}
                                        onChange={(e) => updateLinea(index, { numero_lote: e.target.value })}
                                        disabled={processing}
                                        maxLength={128}
                                        className="h-10"
                                        placeholder={t('modal.linea_lote_placeholder')}
                                    />
                                </FormField>
                                <FormField
                                    id={`compra-linea-venc-${index}`}
                                    label={t('modal.linea_vencimiento')}
                                    error={fieldErr(`lineas.${index}.fecha_vencimiento`)}
                                    className="min-w-0 sm:col-span-1"
                                >
                                    <Input
                                        id={`compra-linea-venc-${index}`}
                                        type="date"
                                        value={linea.fecha_vencimiento}
                                        onChange={(e) => updateLinea(index, { fecha_vencimiento: e.target.value })}
                                        disabled={processing}
                                        className="h-10"
                                    />
                                </FormField>
                                <div className="flex items-end justify-end sm:col-span-1 lg:col-span-1">
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

        {canCreateProducto ? (
            <ProductoQuickCreateDialog
                open={quickCreateProducto !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setQuickCreateProducto(null);
                    }
                }}
                initialNombre={quickCreateProducto?.nombre ?? ''}
                initialPrecioCompra={quickCreateProducto?.precioCompra}
                unidadOptions={unidadOptions}
                onCreated={(producto) => {
                    appendProducto(producto);

                    if (quickCreateProducto !== null) {
                        updateLinea(quickCreateProducto.lineIndex, { producto_id: producto.id });
                    }

                    setQuickCreateProducto(null);
                }}
            />
        ) : null}
        </>
    );
}
