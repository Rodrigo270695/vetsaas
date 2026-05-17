import { useForm } from '@inertiajs/react';
import { Loader2, Ruler } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOption } from '@/components/ui/combobox';
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
import { Textarea } from '@/components/ui/textarea';
import type { Producto, ProductoCategoriaOption, ProductoUnidadOption } from '../types';
import { UnidadesMedidaManageDialog } from './unidades-medida-manage-dialog';

type ProductoFormModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    producto: Producto | null;
    categoriaOptions: readonly ProductoCategoriaOption[];
    unidadOptions: readonly ProductoUnidadOption[];
    canGestionarUnidadesCatalogo: boolean;
    canEditUnidadesPersonalizadas: boolean;
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
    stock_minimo: string;
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
    stock_minimo: '',
    medicamento: false,
    activo: true,
};

export function ProductoFormModal({
    open,
    onOpenChange,
    producto,
    categoriaOptions,
    unidadOptions,
    canGestionarUnidadesCatalogo,
    canEditUnidadesPersonalizadas,
}: ProductoFormModalProps) {
    const { t } = useTranslation(['productos-inventario', 'common']);
    const isEdit = producto !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm<FormData>(empty);
    const [unidadesDialogOpen, setUnidadesDialogOpen] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        if (!producto) {
            reset();
            clearErrors();

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
            stock_minimo: producto.stock_minimo != null && producto.stock_minimo !== '' ? String(producto.stock_minimo) : '',
            medicamento: producto.medicamento,
            activo: producto.activo,
        });
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, producto?.id]);

    const handleModalOpenChange = useCallback(
        (next: boolean) => {
            onOpenChange(next);

            if (!next) {
                setUnidadesDialogOpen(false);
            }
        },
        [onOpenChange],
    );

    const categoriaComboboxOptions = useMemo<readonly ComboboxOption[]>(
        () => categoriaOptions.map((c) => ({ value: c.id, label: c.nombre })),
        [categoriaOptions],
    );

    const codigosConocidos = useMemo(() => new Set(unidadOptions.map((u) => u.codigo)), [unidadOptions]);

    const legacyUnidad = useMemo((): ProductoUnidadOption | null => {
        if (!data.unidad || codigosConocidos.has(data.unidad)) {
            return null;
        }

        return {
            id: '__legacy__',
            codigo: data.unidad,
            nombre: t('form.unidad_legacy_label', { codigo: data.unidad }),
            es_sistema: true,
        };
    }, [data.unidad, codigosConocidos, t]);

    const { unidadesSistema, unidadesPersonal } = useMemo(() => {
        const sistema = unidadOptions.filter((u) => u.es_sistema);
        const personal = unidadOptions.filter((u) => !u.es_sistema);

        return { unidadesSistema: sistema, unidadesPersonal: personal };
    }, [unidadOptions]);

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

        post('/inventario/productos', { preserveScroll: true, onSuccess });
    };

    return (
        <>
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
                        <FormField id="prod-unidad" label={t('form.unidad')} error={errors.unidad}>
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-stretch">
                                <Select value={data.unidad} onValueChange={(v) => setData('unidad', v)}>
                                    <SelectTrigger id="prod-unidad" className="w-full min-w-0 sm:flex-1" aria-invalid={Boolean(errors.unidad)}>
                                        <SelectValue placeholder={t('form.unidad_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {legacyUnidad && (
                                            <SelectGroup>
                                                <SelectLabel>{t('form.unidad_grupo_actual')}</SelectLabel>
                                                <SelectItem value={legacyUnidad.codigo}>{legacyUnidad.nombre}</SelectItem>
                                            </SelectGroup>
                                        )}
                                        <SelectGroup>
                                            <SelectLabel>{t('form.unidad_grupo_sistema')}</SelectLabel>
                                            {unidadesSistema.map((u) => (
                                                <SelectItem key={u.id} value={u.codigo}>
                                                    {u.nombre} ({u.codigo})
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                        {unidadesPersonal.length > 0 && (
                                            <SelectGroup>
                                                <SelectLabel>{t('form.unidad_grupo_personal')}</SelectLabel>
                                                {unidadesPersonal.map((u) => (
                                                    <SelectItem key={u.id} value={u.codigo}>
                                                        {u.nombre} ({u.codigo})
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        )}
                                    </SelectContent>
                                </Select>
                                {canGestionarUnidadesCatalogo && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="shrink-0 gap-2 sm:w-auto"
                                        onClick={() => setUnidadesDialogOpen(true)}
                                    >
                                        <Ruler className="size-4" aria-hidden />
                                        {t('form.unidades_gestionar')}
                                    </Button>
                                )}
                            </div>
                        </FormField>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
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

            <UnidadesMedidaManageDialog
                open={unidadesDialogOpen}
                onOpenChange={setUnidadesDialogOpen}
                unidadOptions={unidadOptions}
                canEdit={canEditUnidadesPersonalizadas}
                canCreate={canGestionarUnidadesCatalogo}
                unidadSeleccionadaCodigo={data.unidad}
                onUnidadMedidaCreated={(codigo) => setData('unidad', codigo)}
                onUnidadMedidaEliminada={(codigo) => {
                    if (data.unidad === codigo) {
                        setData('unidad', 'UN');
                    }
                }}
            />
        </>
    );
}
