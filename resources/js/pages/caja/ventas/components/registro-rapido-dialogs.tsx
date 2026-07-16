import { Loader2, PackagePlus, Stethoscope } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField, FormModal } from '@/components/forms';
import { UnidadMedidaCombobox, type UnidadMedidaOption } from '@/components/inventario/unidad-medida-combobox';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { toastManager } from '@/lib/toast';
import caja from '@/routes/caja';
import type { ProductoBusqueda, ServicioTarifaBusqueda } from '../types';

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

type JsonBody = {
    producto?: ProductoBusqueda;
    servicio?: ServicioTarifaBusqueda;
    message?: string;
    errors?: Record<string, string[]>;
};

async function postJson(url: string, payload: unknown): Promise<{ status: number; body: JsonBody }> {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(payload),
    });

    const body = (await res.json().catch(() => ({}))) as JsonBody;

    return { status: res.status, body };
}

function mapErrors(body: JsonBody): Record<string, string> {
    const mapped: Record<string, string> = {};
    if (body.errors) {
        for (const [key, msgs] of Object.entries(body.errors)) {
            if (Array.isArray(msgs) && msgs[0]) {
                mapped[key] = msgs[0];
            }
        }
    }

    return mapped;
}

type ProductoRapidoDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    initialNombre: string;
    unidadOptions: readonly UnidadMedidaOption[];
    sedeNombre: string | null;
    onCreated: (producto: ProductoBusqueda) => void;
};

type ProductoForm = {
    nombre: string;
    sku: string;
    unidad: string;
    precio_venta: string;
    stock_inicial: string;
    medicamento: boolean;
    numero_lote: string;
    fecha_vencimiento: string;
};

const emptyProducto: ProductoForm = {
    nombre: '',
    sku: '',
    unidad: 'UN',
    precio_venta: '',
    stock_inicial: '1',
    medicamento: false,
    numero_lote: '',
    fecha_vencimiento: '',
};

export function ProductoRapidoDialog({
    open,
    onOpenChange,
    initialNombre,
    unidadOptions,
    sedeNombre,
    onCreated,
}: ProductoRapidoDialogProps) {
    const { t } = useTranslation(['caja', 'common']);
    const [form, setForm] = useState<ProductoForm>(emptyProducto);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (open) {
            setForm({ ...emptyProducto, nombre: initialNombre.trim() });
            setErrors({});
        }
    }, [open, initialNombre]);

    const canSubmit =
        form.nombre.trim() !== '' &&
        form.precio_venta.trim() !== '' &&
        form.stock_inicial.trim() !== '' &&
        !submitting;

    const submit = async () => {
        if (submitting) {
            return;
        }
        setSubmitting(true);
        setErrors({});

        try {
            const { status, body } = await postJson(caja.ventas.productosRapido.url(), {
                nombre: form.nombre.trim(),
                sku: form.sku.trim() === '' ? null : form.sku.trim(),
                unidad: form.unidad.trim() === '' ? 'UN' : form.unidad.trim().toUpperCase(),
                precio_venta: form.precio_venta.trim(),
                stock_inicial: form.stock_inicial.trim(),
                medicamento: form.medicamento,
                numero_lote: form.numero_lote.trim() === '' ? null : form.numero_lote.trim(),
                fecha_vencimiento: form.fecha_vencimiento.trim() === '' ? null : form.fecha_vencimiento.trim(),
            });

            if (status === 422) {
                setErrors(mapErrors(body));
                if (body.message) {
                    toastManager.error({ title: body.message });
                }

                return;
            }

            if (status !== 201 || !body.producto) {
                toastManager.error({ title: t('caja:ventas.create.rapido_error') });

                return;
            }

            onCreated(body.producto);
            onOpenChange(false);
            toastManager.success({
                title: t('caja:ventas.create.rapido_producto_ok', { producto: body.producto.nombre }),
            });
        } catch {
            toastManager.error({ title: t('caja:ventas.create.rapido_error') });
        } finally {
            setSubmitting(false);
        }
    };

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        e.stopPropagation();
        void submit();
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('caja:ventas.create.rapido_producto_title')}
            description={t('caja:ventas.create.rapido_producto_desc')}
            size="md"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" disabled={submitting} onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="button" disabled={!canSubmit} className="gap-2" onClick={() => void submit()}>
                        {submitting ? <Loader2 className="size-4 animate-spin" aria-hidden /> : <PackagePlus className="size-4" aria-hidden />}
                        {t('caja:ventas.create.rapido_producto_submit')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                {errors.plan_limit ? (
                    <p className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
                        {errors.plan_limit}
                    </p>
                ) : null}
                <FormField id="pr-nombre" label={t('caja:ventas.create.rapido_nombre')} required error={errors.nombre}>
                    <Input
                        id="pr-nombre"
                        value={form.nombre}
                        onChange={(e) => setForm((f) => ({ ...f, nombre: e.target.value }))}
                        disabled={submitting}
                        className="h-10"
                        autoFocus
                    />
                </FormField>
                <div className="grid grid-cols-2 gap-4">
                    <FormField id="pr-precio" label={t('caja:ventas.create.rapido_precio_venta')} required error={errors.precio_venta}>
                        <Input
                            id="pr-precio"
                            type="number"
                            inputMode="decimal"
                            min={0}
                            step="any"
                            value={form.precio_venta}
                            onChange={(e) => setForm((f) => ({ ...f, precio_venta: e.target.value }))}
                            disabled={submitting}
                            className="h-10 tabular-nums"
                            placeholder="0.00"
                        />
                    </FormField>
                    <FormField id="pr-stock" label={t('caja:ventas.create.rapido_stock')} required error={errors.stock_inicial}>
                        <Input
                            id="pr-stock"
                            type="number"
                            inputMode="decimal"
                            min={0.001}
                            step="any"
                            value={form.stock_inicial}
                            onChange={(e) => setForm((f) => ({ ...f, stock_inicial: e.target.value }))}
                            disabled={submitting}
                            className="h-10 tabular-nums"
                        />
                    </FormField>
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <FormField id="pr-unidad" label={t('caja:ventas.create.rapido_unidad')} error={errors.unidad}>
                        <UnidadMedidaCombobox
                            id="pr-unidad"
                            value={form.unidad}
                            onChange={(codigo) => setForm((f) => ({ ...f, unidad: codigo }))}
                            unidadOptions={unidadOptions}
                            canCreate={false}
                            disabled={submitting}
                            aria-invalid={Boolean(errors.unidad)}
                            translationNs="productos-inventario"
                        />
                    </FormField>
                    <FormField id="pr-sku" label={t('caja:ventas.create.rapido_sku')} error={errors.sku}>
                        <Input
                            id="pr-sku"
                            value={form.sku}
                            onChange={(e) => setForm((f) => ({ ...f, sku: e.target.value }))}
                            disabled={submitting}
                            className="h-10 font-mono"
                            maxLength={64}
                        />
                    </FormField>
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <FormField id="pr-lote" label={t('caja:ventas.create.rapido_lote')} error={errors.numero_lote}>
                        <Input
                            id="pr-lote"
                            value={form.numero_lote}
                            onChange={(e) => setForm((f) => ({ ...f, numero_lote: e.target.value }))}
                            disabled={submitting}
                            className="h-10"
                            maxLength={128}
                        />
                    </FormField>
                    <FormField id="pr-venc" label={t('caja:ventas.create.rapido_vencimiento')} error={errors.fecha_vencimiento}>
                        <Input
                            id="pr-venc"
                            type="date"
                            value={form.fecha_vencimiento}
                            onChange={(e) => setForm((f) => ({ ...f, fecha_vencimiento: e.target.value }))}
                            disabled={submitting}
                            className="h-10"
                        />
                    </FormField>
                </div>
                <label className="flex cursor-pointer items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.medicamento}
                        onCheckedChange={(v) => setForm((f) => ({ ...f, medicamento: v === true }))}
                        disabled={submitting}
                    />
                    <span>{t('caja:ventas.create.rapido_medicamento')}</span>
                </label>
                {sedeNombre ? (
                    <p className="text-[11px] leading-snug text-muted-foreground">
                        {t('caja:ventas.create.rapido_stock_hint', { sede: sedeNombre })}
                    </p>
                ) : null}
            </div>
        </FormModal>
    );
}

type ServicioRapidoDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    initialNombre: string;
    initialPrecio: string;
    onCreated: (servicio: ServicioTarifaBusqueda) => void;
};

type ServicioForm = {
    nombre: string;
    precio_lista: string;
    categoria: string;
    duracion_minutos: string;
};

const emptyServicio: ServicioForm = {
    nombre: '',
    precio_lista: '',
    categoria: '',
    duracion_minutos: '60',
};

export function ServicioRapidoDialog({
    open,
    onOpenChange,
    initialNombre,
    initialPrecio,
    onCreated,
}: ServicioRapidoDialogProps) {
    const { t } = useTranslation(['caja', 'common']);
    const [form, setForm] = useState<ServicioForm>(emptyServicio);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (open) {
            setForm({
                ...emptyServicio,
                nombre: initialNombre.trim(),
                precio_lista: initialPrecio.trim(),
            });
            setErrors({});
        }
    }, [open, initialNombre, initialPrecio]);

    const canSubmit = form.nombre.trim().length >= 2 && form.precio_lista.trim() !== '' && !submitting;

    const submit = async () => {
        if (submitting) {
            return;
        }
        setSubmitting(true);
        setErrors({});

        try {
            const { status, body } = await postJson(caja.ventas.serviciosRapido.url(), {
                nombre: form.nombre.trim(),
                precio_lista: form.precio_lista.trim(),
                categoria: form.categoria.trim() === '' ? null : form.categoria.trim(),
                duracion_minutos: form.duracion_minutos.trim() === '' ? 60 : Number(form.duracion_minutos),
            });

            if (status === 422) {
                setErrors(mapErrors(body));
                if (body.message) {
                    toastManager.error({ title: body.message });
                }

                return;
            }

            if (status !== 201 || !body.servicio) {
                toastManager.error({ title: t('caja:ventas.create.rapido_error') });

                return;
            }

            onCreated(body.servicio);
            onOpenChange(false);
            toastManager.success({
                title: t('caja:ventas.create.rapido_servicio_ok', { servicio: body.servicio.nombre }),
            });
        } catch {
            toastManager.error({ title: t('caja:ventas.create.rapido_error') });
        } finally {
            setSubmitting(false);
        }
    };

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        e.stopPropagation();
        void submit();
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('caja:ventas.create.rapido_servicio_title')}
            description={t('caja:ventas.create.rapido_servicio_desc')}
            size="md"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" disabled={submitting} onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="button" disabled={!canSubmit} className="gap-2" onClick={() => void submit()}>
                        {submitting ? <Loader2 className="size-4 animate-spin" aria-hidden /> : <Stethoscope className="size-4" aria-hidden />}
                        {t('caja:ventas.create.rapido_servicio_submit')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FormField id="sr-nombre" label={t('caja:ventas.create.rapido_nombre')} required error={errors.nombre}>
                    <Input
                        id="sr-nombre"
                        value={form.nombre}
                        onChange={(e) => setForm((f) => ({ ...f, nombre: e.target.value }))}
                        disabled={submitting}
                        className="h-10"
                        autoFocus
                    />
                </FormField>
                <div className="grid grid-cols-2 gap-4">
                    <FormField id="sr-precio" label={t('caja:ventas.create.rapido_precio_servicio')} required error={errors.precio_lista}>
                        <Input
                            id="sr-precio"
                            type="number"
                            inputMode="decimal"
                            min={0}
                            step="any"
                            value={form.precio_lista}
                            onChange={(e) => setForm((f) => ({ ...f, precio_lista: e.target.value }))}
                            disabled={submitting}
                            className="h-10 tabular-nums"
                            placeholder="0.00"
                        />
                    </FormField>
                    <FormField id="sr-duracion" label={t('caja:ventas.create.rapido_duracion')} error={errors.duracion_minutos}>
                        <Input
                            id="sr-duracion"
                            type="number"
                            inputMode="numeric"
                            min={5}
                            max={480}
                            step={5}
                            value={form.duracion_minutos}
                            onChange={(e) => setForm((f) => ({ ...f, duracion_minutos: e.target.value }))}
                            disabled={submitting}
                            className="h-10 tabular-nums"
                        />
                    </FormField>
                </div>
                <FormField id="sr-categoria" label={t('caja:ventas.create.rapido_categoria')} error={errors.categoria}>
                    <Input
                        id="sr-categoria"
                        value={form.categoria}
                        onChange={(e) => setForm((f) => ({ ...f, categoria: e.target.value }))}
                        disabled={submitting}
                        className="h-10"
                        maxLength={80}
                    />
                </FormField>
            </div>
        </FormModal>
    );
}
