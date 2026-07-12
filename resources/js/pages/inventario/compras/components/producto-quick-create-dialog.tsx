import { Loader2 } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
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
import { toastManager } from '@/lib/toast';
import inventario from '@/routes/inventario';
import type { ProductoOptionCompra, ProductoUnidadOptionCompra } from '../types';

export type ProductoQuickCreated = ProductoOptionCompra;

type ProductoQuickCreateDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    initialNombre: string;
    initialPrecioCompra?: string;
    unidadOptions: readonly ProductoUnidadOptionCompra[];
    onCreated: (producto: ProductoQuickCreated) => void;
};

type QuickForm = {
    nombre: string;
    sku: string;
    unidad: string;
    precio_compra: string;
    medicamento: boolean;
};

export function ProductoQuickCreateDialog({
    open,
    onOpenChange,
    initialNombre,
    initialPrecioCompra,
    unidadOptions,
    onCreated,
}: ProductoQuickCreateDialogProps) {
    const { t } = useTranslation(['compras-inventario', 'common']);
    const [form, setForm] = useState<QuickForm>({
        nombre: '',
        sku: '',
        unidad: 'UN',
        precio_compra: '',
        medicamento: false,
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        setForm({
            nombre: initialNombre.trim(),
            sku: '',
            unidad: 'UN',
            precio_compra: initialPrecioCompra?.trim() ?? '',
            medicamento: false,
        });
        setErrors({});
    }, [open, initialNombre, initialPrecioCompra]);

    const unidadesSistema = unidadOptions.filter((u) => u.es_sistema);
    const unidadesPersonal = unidadOptions.filter((u) => !u.es_sistema);

    const submitQuickProduct = async () => {
        setSubmitting(true);
        setErrors({});

        try {
            const res = await fetch(inventario.productos.quick.url(), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        nombre: form.nombre.trim(),
                        sku: form.sku.trim() === '' ? null : form.sku.trim(),
                        unidad: form.unidad.trim() === '' ? 'UN' : form.unidad.trim().toUpperCase(),
                        precio_compra: form.precio_compra.trim() === '' ? null : form.precio_compra.trim(),
                        medicamento: form.medicamento,
                    }),
                });

                const body = (await res.json()) as {
                    data?: ProductoQuickCreated;
                    message?: string;
                    errors?: Record<string, string[]>;
                };

                if (!res.ok) {
                    if (body.errors) {
                        const mapped: Record<string, string> = {};
                        for (const [key, msgs] of Object.entries(body.errors)) {
                            if (Array.isArray(msgs) && msgs[0]) {
                                mapped[key] = msgs[0];
                            }
                        }
                        setErrors(mapped);
                    }

                    toastManager.error({
                        title: body.message ?? t('producto_quick.error'),
                    });

                    return;
                }

                if (!body.data?.id) {
                    toastManager.error({ title: t('producto_quick.error') });

                    return;
                }

                onCreated(body.data);
                onOpenChange(false);
                toastManager.success({ title: t('producto_quick.success') });
        } catch {
            toastManager.error({ title: t('producto_quick.error') });
        } finally {
            setSubmitting(false);
        }
    };

    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        e.stopPropagation();
        void submitQuickProduct();
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            title={t('producto_quick.title')}
            description={t('producto_quick.description')}
            size="md"
            onSubmit={onSubmit}
            footer={
                <>
                    <Button type="button" variant="outline" disabled={submitting} onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={submitting || form.nombre.trim() === ''}
                        className="gap-2"
                        onClick={() => void submitQuickProduct()}
                    >
                        {submitting && <Loader2 className="size-4 animate-spin" />}
                        {t('producto_quick.submit')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FormField id="pq-nombre" label={t('producto_quick.nombre')} required error={errors.nombre}>
                    <Input
                        id="pq-nombre"
                        value={form.nombre}
                        onChange={(e) => setForm((f) => ({ ...f, nombre: e.target.value }))}
                        disabled={submitting}
                        className="h-10"
                        autoFocus
                    />
                </FormField>
                <FormField id="pq-sku" label={t('producto_quick.sku')} error={errors.sku}>
                    <Input
                        id="pq-sku"
                        value={form.sku}
                        onChange={(e) => setForm((f) => ({ ...f, sku: e.target.value }))}
                        disabled={submitting}
                        className="h-10 font-mono"
                        maxLength={64}
                    />
                </FormField>
                <FormField id="pq-unidad" label={t('producto_quick.unidad')} error={errors.unidad}>
                    <Select
                        value={form.unidad}
                        onValueChange={(v) => setForm((f) => ({ ...f, unidad: v }))}
                        disabled={submitting}
                    >
                        <SelectTrigger id="pq-unidad" className="h-10 w-full cursor-pointer">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {unidadesSistema.length > 0 ? (
                                <SelectGroup>
                                    <SelectLabel>{t('producto_quick.unidad_sistema')}</SelectLabel>
                                    {unidadesSistema.map((u) => (
                                        <SelectItem key={u.codigo} value={u.codigo}>
                                            {u.nombre} ({u.codigo})
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            ) : null}
                            {unidadesPersonal.length > 0 ? (
                                <SelectGroup>
                                    <SelectLabel>{t('producto_quick.unidad_personal')}</SelectLabel>
                                    {unidadesPersonal.map((u) => (
                                        <SelectItem key={u.codigo} value={u.codigo}>
                                            {u.nombre} ({u.codigo})
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            ) : null}
                        </SelectContent>
                    </Select>
                </FormField>
                <FormField id="pq-precio" label={t('producto_quick.precio_compra')} error={errors.precio_compra}>
                    <Input
                        id="pq-precio"
                        type="number"
                        inputMode="decimal"
                        min={0}
                        step="any"
                        value={form.precio_compra}
                        onChange={(e) => setForm((f) => ({ ...f, precio_compra: e.target.value }))}
                        disabled={submitting}
                        className="h-10"
                    />
                </FormField>
                <label className="flex cursor-pointer items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.medicamento}
                        onCheckedChange={(v) => setForm((f) => ({ ...f, medicamento: v === true }))}
                        disabled={submitting}
                    />
                    <span>{t('producto_quick.medicamento')}</span>
                </label>
            </div>
        </FormModal>
    );
}
