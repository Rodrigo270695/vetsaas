import { router } from '@inertiajs/react';
import { Loader2, PackagePlus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import type {
    ClinicaPaqueteProductoAsignado,
    ClinicaPaqueteProductoCatalogo,
    ClinicaPaqueteProductosResponse,
} from '../types';

type ServicioLite = {
    id: string;
    nombre: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    servicio: ServicioLite | null;
    canUpdate: boolean;
};

type RowState = {
    producto_id: string;
    nombre: string;
    sku: string | null;
    cantidad: string;
};

export function ClinicaPaqueteProductosModal({ open, onOpenChange, servicio, canUpdate }: Props) {
    const { t } = useTranslation(['tarifas-servicios', 'common']);

    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [catalogo, setCatalogo] = useState<ClinicaPaqueteProductoCatalogo[]>([]);
    const [rows, setRows] = useState<RowState[]>([]);

    useEffect(() => {
        if (!open || !servicio) {
            return;
        }

        const controller = new AbortController();
        setLoading(true);

        fetch(`/configuracion/tarifas/clinica/servicios/${servicio.id}/productos`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((res) =>
                res.ok ? (res.json() as Promise<ClinicaPaqueteProductosResponse>) : Promise.reject(res),
            )
            .then((data) => {
                setCatalogo(data.catalogo ?? []);
                setRows(
                    (data.asignados ?? []).map((a: ClinicaPaqueteProductoAsignado) => ({
                        producto_id: a.producto_id,
                        nombre: a.nombre,
                        sku: a.sku ?? null,
                        cantidad: a.cantidad,
                    })),
                );
            })
            .catch((err) => {
                if (err?.name !== 'AbortError') {
                    setCatalogo([]);
                    setRows([]);
                }
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [open, servicio]);

    const usedIds = useMemo(() => new Set(rows.map((r) => r.producto_id)), [rows]);

    const options = useMemo<ComboboxOption[]>(
        () =>
            catalogo
                .filter((c) => !usedIds.has(c.id))
                .map((c) => ({
                    value: c.id,
                    label: c.sku ? `${c.nombre} · ${c.sku}` : c.nombre,
                })),
        [catalogo, usedIds],
    );

    const addFromCombobox = (value: string | null) => {
        if (!value) {
            return;
        }

        const existing = catalogo.find((c) => c.id === value);
        if (!existing || usedIds.has(existing.id)) {
            return;
        }

        setRows((prev) => [
            ...prev,
            {
                producto_id: existing.id,
                nombre: existing.nombre,
                sku: existing.sku ?? null,
                cantidad: '1',
            },
        ]);
    };

    const updateCantidad = (index: number, cantidad: string) => {
        setRows((prev) => prev.map((r, i) => (i === index ? { ...r, cantidad } : r)));
    };

    const removeRow = (index: number) => {
        setRows((prev) => prev.filter((_, i) => i !== index));
    };

    const submit = () => {
        if (!servicio) {
            return;
        }

        setSubmitting(true);
        router.put(
            `/configuracion/tarifas/clinica/servicios/${servicio.id}/productos`,
            {
                items: rows.map((r) => ({
                    producto_id: r.producto_id,
                    cantidad: Number(r.cantidad) || 0,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <FormModal
            open={open}
            onOpenChange={onOpenChange}
            size="lg"
            title={t('paquete.title')}
            description={
                servicio
                    ? t('paquete.description', { servicio: servicio.nombre })
                    : t('paquete.description_generic')
            }
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
            footer={
                <>
                    <div className="mr-auto flex flex-col text-left">
                        <span className="text-xs text-muted-foreground">
                            {t('paquete.footer_total', { count: rows.length })}
                        </span>
                    </div>
                    <Button type="button" variant="outline" disabled={submitting} onClick={() => onOpenChange(false)}>
                        {t('form.cancelar')}
                    </Button>
                    <Button type="submit" disabled={submitting || !canUpdate} className="gap-2">
                        {submitting ? <Loader2 className="size-4 animate-spin" /> : null}
                        {t('form.guardar')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-5">
                <div className="space-y-1.5">
                    <p className="text-sm font-medium text-foreground">{t('paquete.add_label')}</p>
                    <Combobox
                        options={options}
                        value={null}
                        onChange={addFromCombobox}
                        placeholder={t('paquete.add_placeholder')}
                        searchPlaceholder={t('paquete.search_placeholder')}
                        emptyMessage={t('paquete.empty_catalog')}
                        clearable={false}
                        creatable={false}
                        disabled={!canUpdate}
                    />
                </div>

                {loading ? (
                    <div className="flex items-center justify-center gap-2 rounded-lg border border-dashed border-border/60 py-10 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        {t('common:loading', { defaultValue: 'Cargando…' })}
                    </div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border/60 py-10 text-center">
                        <PackagePlus className="size-6 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">{t('paquete.empty_rows')}</p>
                    </div>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {rows.map((row, index) => (
                            <li
                                key={row.producto_id}
                                className="flex items-center gap-3 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5"
                            >
                                <div className="flex min-w-0 flex-1 flex-col">
                                    <span className="truncate text-sm font-medium text-foreground">{row.nombre}</span>
                                    {row.sku ? (
                                        <span className="text-[0.7rem] text-muted-foreground">{row.sku}</span>
                                    ) : null}
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <Input
                                        type="number"
                                        min={0.001}
                                        step="0.001"
                                        inputMode="decimal"
                                        value={row.cantidad}
                                        onChange={(e) => updateCantidad(index, e.target.value)}
                                        disabled={!canUpdate}
                                        className="w-24 tabular-nums"
                                        placeholder="1"
                                        aria-label={t('paquete.cantidad_label', { nombre: row.nombre })}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    disabled={!canUpdate}
                                    onClick={() => removeRow(index)}
                                    className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                                    aria-label={t('paquete.remove_label', { nombre: row.nombre })}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </FormModal>
    );
}
