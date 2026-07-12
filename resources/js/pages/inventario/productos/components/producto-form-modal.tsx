import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { UnidadMedidaCombobox, type UnidadMedidaOption } from '@/components/inventario/unidad-medida-combobox';
import { FormField, FormModal, SedeFormField } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { resolveDefaultSedeIdOrEmpty } from '@/lib/default-sede';
import { enqueueIfOffline } from '@/lib/offline/enqueue-if-offline';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import type { Producto, ProductoCategoriaOption, ProductoSedeOption } from '../types';

type ProductoFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    producto: Producto | null;
    categoriaOptions: readonly ProductoCategoriaOption[];
    unidadOptions: readonly UnidadMedidaOption[];
    sedeOptions: readonly ProductoSedeOption[];
    canCreateUnidad: boolean;
    defaultSedeId?: string;
};

type FormData = {
    categoria_id: string | null;
    nombre: string;
    slug: string;
    descripcion: string;
    sku: string;
    codigo_barras: string;
    unidad: string;
    precio_venta: string;
    precio_compra: string;
    stock_minimo: string;
    stock_inicial_sede_id: string;
    stock_inicial_cantidad: string;
    numero_lote: string;
    fecha_vencimiento: string;
    medicamento: boolean;
    activo: boolean;
};

const empty: FormData = {
    categoria_id: null,
    nombre: '',
    slug: '',
    descripcion: '',
    sku: '',
    codigo_barras: '',
    unidad: 'UN',
    precio_venta: '',
    precio_compra: '',
    stock_minimo: '',
    stock_inicial_sede_id: '',
    stock_inicial_cantidad: '',
    numero_lote: '',
    fecha_vencimiento: '',
    medicamento: false,
    activo: true,
};

export function ProductoFormModal({
    open,
    onOpenChange,
    producto,
    categoriaOptions,
    unidadOptions,
    sedeOptions,
    canCreateUnidad,
    defaultSedeId = '',
}: ProductoFormModalProps) {
    const { t } = useTranslation(['productos-inventario', 'common', 'offline']);
    const { refreshPending } = useOfflineSync();
    const isEdit = producto !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm<FormData>(empty);
    const [unidadesLocal, setUnidadesLocal] = useState<UnidadMedidaOption[]>([]);

    useEffect(() => {
        if (!open) {
            return;
        }

        setUnidadesLocal([...unidadOptions]);

        if (!producto) {
            reset();
            clearErrors();
            setData({
                ...empty,
                stock_inicial_sede_id:
                    defaultSedeId && defaultSedeId !== ''
                        ? defaultSedeId
                        : resolveDefaultSedeIdOrEmpty(sedeOptions),
            });

            return;
        }

        setData({
            categoria_id: producto.categoria_id,
            nombre: producto.nombre,
            slug: producto.slug ?? '',
            descripcion: producto.descripcion ?? '',
            sku: producto.sku ?? '',
            codigo_barras: producto.codigo_barras ?? '',
            unidad: (producto.unidad || 'UN').toUpperCase(),
            precio_venta: producto.precio_venta ?? '',
            precio_compra: producto.precio_compra ?? '',
            stock_minimo: producto.stock_minimo != null && producto.stock_minimo !== '' ? String(producto.stock_minimo) : '',
            stock_inicial_sede_id: '',
            stock_inicial_cantidad: '',
            numero_lote: '',
            fecha_vencimiento: '',
            medicamento: producto.medicamento,
            activo: producto.activo,
        });
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, producto?.id, unidadOptions, sedeOptions, defaultSedeId]);

    const handleModalOpenChange = useCallback(
        (next: boolean) => {
            onOpenChange(next);
        },
        [onOpenChange],
    );

    const categoriaComboboxOptions = useMemo<readonly ComboboxOption[]>(
        () => categoriaOptions.map((c) => ({ value: c.id, label: c.nombre })),
        [categoriaOptions],
    );

    const buildCreatePayload = (raw: FormData): Record<string, unknown> => {
        const slug = raw.slug.trim().toLowerCase();
        const sku = raw.sku.trim();
        const barras = raw.codigo_barras.trim();
        const precioVenta = raw.precio_venta.trim();
        const precioCompra = raw.precio_compra.trim();
        const stockMin = raw.stock_minimo.trim();
        const stockCantidad = raw.stock_inicial_cantidad.trim();
        const stockSede = raw.stock_inicial_sede_id.trim();
        const lote = raw.numero_lote.trim();
        const venc = raw.fecha_vencimiento.trim();

        return {
            categoria_id: raw.categoria_id,
            nombre: raw.nombre.trim(),
            slug: slug === '' ? null : slug,
            descripcion: raw.descripcion.trim() === '' ? null : raw.descripcion.trim(),
            sku: sku === '' ? null : sku,
            codigo_barras: barras === '' ? null : barras,
            unidad: raw.unidad.trim() === '' ? 'UN' : raw.unidad.trim().toUpperCase().slice(0, 20),
            precio_venta: precioVenta === '' ? null : precioVenta,
            precio_compra: precioCompra === '' ? null : precioCompra,
            stock_minimo: stockMin === '' ? null : stockMin,
            stock_inicial_sede_id: stockCantidad === '' ? null : stockSede === '' ? null : stockSede,
            stock_inicial_cantidad: stockCantidad === '' ? null : stockCantidad,
            numero_lote: lote === '' ? null : lote,
            fecha_vencimiento: venc === '' ? null : venc,
            medicamento: raw.medicamento,
            activo: raw.activo,
        };
    };

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        const onSuccess = () => {
            handleModalOpenChange(false);
            reset();
            clearErrors();
        };

        if (isEdit && producto) {
            put(`/inventario/productos/${producto.id}`, { preserveScroll: true, onSuccess });

            return;
        }

        void (async () => {
            const queued = await enqueueIfOffline(
                'inventario.producto.create',
                buildCreatePayload(data),
                {
                    refreshPending,
                    onSuccess,
                    title: t('offline:producto.queued_title'),
                    description: t('offline:producto.queued_body'),
                },
            );

            if (queued) {
                return;
            }

            post('/inventario/productos', { preserveScroll: true, onSuccess });
        })();
    };

    return (
        <FormModal
            open={open}
            onOpenChange={handleModalOpenChange}
                title={isEdit ? t('form.title_edit') : t('form.title_create')}
                description={t('description')}
                size="lg"
                onSubmit={onSubmit}
                footer={
                    <>
                        <Button type="button" variant="outline" disabled={processing} onClick={() => handleModalOpenChange(false)}>
                            {t('common:actions.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || data.nombre.trim() === ''} className="gap-2">
                            {processing && <Loader2 className="size-4 animate-spin" />}
                            {isEdit ? t('form.submit_edit') : t('form.submit_create')}
                        </Button>
                    </>
                }
            >
                <div className="grid gap-4">
                    {errors.plan_limit ? (
                        <p
                            className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                            role="alert"
                        >
                            {errors.plan_limit}
                        </p>
                    ) : null}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="prod-nombre" label={t('form.nombre')} error={errors.nombre} required className="min-w-0">
                            <Input id="prod-nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                        </FormField>
                        <FormField id="prod-cat" label={t('form.categoria')} error={errors.categoria_id} className="min-w-0">
                            <Combobox
                                id="prod-cat"
                                options={categoriaComboboxOptions}
                                value={data.categoria_id}
                                onChange={(v) => setData('categoria_id', v)}
                                placeholder={t('form.categoria_none')}
                                searchPlaceholder={t('form.categoria_search')}
                                emptyMessage={t('form.categoria_empty')}
                                aria-invalid={Boolean(errors.categoria_id)}
                            />
                        </FormField>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="prod-sku" label={t('form.sku')} error={errors.sku}>
                            <Input id="prod-sku" value={data.sku} onChange={(e) => setData('sku', e.target.value)} />
                        </FormField>
                        <FormField id="prod-barras" label={t('form.codigo_barras')} error={errors.codigo_barras}>
                            <Input
                                id="prod-barras"
                                value={data.codigo_barras}
                                onChange={(e) => setData('codigo_barras', e.target.value)}
                            />
                        </FormField>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="prod-slug" label={t('form.slug')} error={errors.slug}>
                            <Input id="prod-slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} />
                        </FormField>
                        <FormField id="prod-unidad" label={t('form.unidad')} error={errors.unidad} className="min-w-0">
                            <UnidadMedidaCombobox
                                id="prod-unidad"
                                value={data.unidad}
                                onChange={(codigo) => setData('unidad', codigo)}
                                unidadOptions={unidadesLocal}
                                onUnidadOptionsChange={setUnidadesLocal}
                                canCreate={canCreateUnidad}
                                disabled={processing}
                                aria-invalid={Boolean(errors.unidad)}
                            />
                        </FormField>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="prod-precio-compra" label={t('form.precio_compra')} error={errors.precio_compra}>
                            <Input
                                id="prod-precio-compra"
                                type="number"
                                inputMode="decimal"
                                min={0}
                                step="0.01"
                                value={data.precio_compra}
                                onChange={(e) => setData('precio_compra', e.target.value)}
                            />
                        </FormField>

                        <FormField id="prod-precio" label={t('form.precio_venta')} error={errors.precio_venta}>
                            <Input
                                id="prod-precio"
                                type="number"
                                inputMode="decimal"
                                min={0}
                                step="0.01"
                                value={data.precio_venta}
                                onChange={(e) => setData('precio_venta', e.target.value)}
                            />
                        </FormField>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="prod-stock-min"
                            label={t('form.stock_minimo')}
                            error={errors.stock_minimo}
                            hint={t('form.stock_minimo_hint')}
                        >
                            <Input
                                id="prod-stock-min"
                                type="number"
                                inputMode="decimal"
                                min={0}
                                step="0.001"
                                value={data.stock_minimo}
                                onChange={(e) => setData('stock_minimo', e.target.value)}
                            />
                        </FormField>
                    </div>

                    {!isEdit ? (
                        <div className="space-y-3 rounded-lg border border-border p-3">
                            <div>
                                <p className="text-sm font-medium text-foreground">{t('form.stock_inicial_title')}</p>
                                <p className="text-xs text-muted-foreground">{t('form.stock_inicial_hint')}</p>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SedeFormField
                                    id="prod-stock-sede"
                                    label={t('form.stock_inicial_sede')}
                                    value={data.stock_inicial_sede_id === '' ? null : data.stock_inicial_sede_id}
                                    onChange={(v) => setData('stock_inicial_sede_id', v ?? '')}
                                    sedes={sedeOptions}
                                    error={errors.stock_inicial_sede_id}
                                    disabled={processing || sedeOptions.length === 0}
                                />
                                <FormField
                                    id="prod-stock-cant"
                                    label={t('form.stock_inicial_cantidad')}
                                    error={errors.stock_inicial_cantidad}
                                    className="min-w-0"
                                >
                                    <Input
                                        id="prod-stock-cant"
                                        type="number"
                                        inputMode="decimal"
                                        min={0.001}
                                        step="any"
                                        value={data.stock_inicial_cantidad}
                                        onChange={(e) => setData('stock_inicial_cantidad', e.target.value)}
                                        disabled={processing}
                                        className="h-10"
                                    />
                                </FormField>
                                <FormField
                                    id="prod-lote"
                                    label={t('form.numero_lote')}
                                    error={errors.numero_lote}
                                    className="min-w-0"
                                >
                                    <Input
                                        id="prod-lote"
                                        value={data.numero_lote}
                                        onChange={(e) => setData('numero_lote', e.target.value)}
                                        disabled={processing}
                                        maxLength={128}
                                        className="h-10"
                                        placeholder={t('form.numero_lote_placeholder')}
                                    />
                                </FormField>
                                <FormField
                                    id="prod-venc"
                                    label={t('form.fecha_vencimiento')}
                                    error={errors.fecha_vencimiento}
                                    className="min-w-0"
                                >
                                    <Input
                                        id="prod-venc"
                                        type="date"
                                        value={data.fecha_vencimiento}
                                        onChange={(e) => setData('fecha_vencimiento', e.target.value)}
                                        disabled={processing}
                                        className="h-10"
                                    />
                                </FormField>
                            </div>
                        </div>
                    ) : null}

                    <FormField id="prod-desc" label={t('form.descripcion')} error={errors.descripcion}>
                        <Textarea
                            id="prod-desc"
                            value={data.descripcion}
                            onChange={(e) => setData('descripcion', e.target.value)}
                            rows={3}
                        />
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField id="prod-med" label={t('form.medicamento')}>
                            <label htmlFor="prod-med" className="flex items-center gap-3 text-sm">
                                <Checkbox
                                    id="prod-med"
                                    checked={data.medicamento}
                                    onCheckedChange={(checked) => setData('medicamento', Boolean(checked))}
                                />
                                <span>{t('form.medicamento_label')}</span>
                            </label>
                        </FormField>
                        <FormField id="prod-activo" label={t('form.estado')}>
                            <label htmlFor="prod-activo" className="flex items-center gap-3 text-sm">
                                <Checkbox
                                    id="prod-activo"
                                    checked={data.activo}
                                    onCheckedChange={(checked) => setData('activo', Boolean(checked))}
                                />
                                <span>{t('form.activo_label')}</span>
                            </label>
                        </FormField>
                    </div>
                </div>
            </FormModal>
    );
}
