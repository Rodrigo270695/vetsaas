import { router } from '@inertiajs/react';
import { Loader2, PackagePlus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FormModal } from '@/components/forms';
import { Button } from '@/components/ui/button';
import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import type {
    GroomingInsumoAsignado,
    GroomingInsumoCatalogo,
    GroomingInsumosResponse,
} from '../types';

type ServicioLite = {
    id: string;
    nombre: string;
    moneda: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    servicio: ServicioLite | null;
    canUpdate: boolean;
};

type RowState = {
    /** Id del insumo del catálogo, o null si es uno nuevo a crear. */
    grooming_insumo_id: string | null;
    nombre: string;
    precio: string;
};

function formatPrecio(amount: number, moneda: string) {
    const cur = moneda === 'USD' ? 'USD' : 'PEN';

    return new Intl.NumberFormat(undefined, { style: 'currency', currency: cur }).format(
        Number.isFinite(amount) ? amount : 0,
    );
}

export function GroomingInsumosModal({ open, onOpenChange, servicio, canUpdate }: Props) {
    const { t } = useTranslation(['tarifas-servicios', 'common']);

    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [catalogo, setCatalogo] = useState<GroomingInsumoCatalogo[]>([]);
    const [rows, setRows] = useState<RowState[]>([]);
    const moneda = servicio?.moneda ?? 'PEN';

    useEffect(() => {
        if (!open || !servicio) {
            return;
        }

        const controller = new AbortController();
        setLoading(true);

        fetch(`/configuracion/tarifas/grooming/servicios/${servicio.id}/insumos`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((res) => (res.ok ? (res.json() as Promise<GroomingInsumosResponse>) : Promise.reject(res)))
            .then((data) => {
                setCatalogo(data.catalogo ?? []);
                setRows(
                    (data.asignados ?? []).map((a: GroomingInsumoAsignado) => ({
                        grooming_insumo_id: a.grooming_insumo_id,
                        nombre: a.nombre,
                        precio: a.precio,
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

    const usedIds = useMemo(
        () => new Set(rows.map((r) => r.grooming_insumo_id).filter(Boolean) as string[]),
        [rows],
    );
    const usedNombres = useMemo(
        () => new Set(rows.map((r) => r.nombre.trim().toLowerCase())),
        [rows],
    );

    const options = useMemo<ComboboxOption[]>(
        () =>
            catalogo
                .filter((c) => !usedIds.has(c.id))
                .map((c) => ({ value: c.id, label: c.nombre })),
        [catalogo, usedIds],
    );

    const addFromCombobox = (value: string | null) => {
        if (!value) {
            return;
        }

        const existing = catalogo.find((c) => c.id === value);

        if (existing) {
            if (usedIds.has(existing.id)) {
                return;
            }
            setRows((prev) => [
                ...prev,
                { grooming_insumo_id: existing.id, nombre: existing.nombre, precio: '' },
            ]);
            return;
        }

        const nombre = value.trim();
        if (nombre === '' || usedNombres.has(nombre.toLowerCase())) {
            return;
        }
        setRows((prev) => [...prev, { grooming_insumo_id: null, nombre, precio: '' }]);
    };

    const updatePrecio = (index: number, precio: string) => {
        setRows((prev) => prev.map((r, i) => (i === index ? { ...r, precio } : r)));
    };

    const removeRow = (index: number) => {
        setRows((prev) => prev.filter((_, i) => i !== index));
    };

    const total = useMemo(
        () => rows.reduce((acc, r) => acc + (Number(r.precio) || 0), 0),
        [rows],
    );

    const submit = () => {
        if (!servicio) {
            return;
        }

        setSubmitting(true);
        router.put(
            `/configuracion/tarifas/grooming/servicios/${servicio.id}/insumos`,
            {
                items: rows.map((r) => ({
                    grooming_insumo_id: r.grooming_insumo_id,
                    nombre: r.nombre,
                    precio: Number(r.precio) || 0,
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
            title={t('insumos.title')}
            description={
                servicio
                    ? t('insumos.description', { servicio: servicio.nombre })
                    : t('insumos.description_generic')
            }
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
            footer={
                <>
                    <div className="mr-auto flex flex-col text-left">
                        <span className="text-xs text-muted-foreground">
                            {t('insumos.footer_total', { count: rows.length })}
                        </span>
                        <span className="text-base font-semibold tabular-nums text-foreground">
                            {formatPrecio(total, moneda)}
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
                    <p className="text-sm font-medium text-foreground">{t('insumos.add_label')}</p>
                    <Combobox
                        options={options}
                        value={null}
                        onChange={addFromCombobox}
                        placeholder={t('insumos.add_placeholder')}
                        searchPlaceholder={t('insumos.search_placeholder')}
                        emptyMessage={t('insumos.empty_catalog')}
                        clearable={false}
                        creatable={canUpdate}
                        createOptionLabel={(q) => t('insumos.create_option', { nombre: q })}
                        disabled={!canUpdate}
                    />
                    <p className="text-xs text-muted-foreground">{t('insumos.add_hint')}</p>
                </div>

                {loading ? (
                    <div className="flex items-center justify-center gap-2 rounded-lg border border-dashed border-border/60 py-10 text-sm text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        {t('common:loading', { defaultValue: 'Cargando…' })}
                    </div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border/60 py-10 text-center">
                        <PackagePlus className="size-6 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">{t('insumos.empty_rows')}</p>
                    </div>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {rows.map((row, index) => (
                            <li
                                key={row.grooming_insumo_id ?? `new:${row.nombre}`}
                                className="flex items-center gap-3 rounded-lg border border-border/60 bg-muted/20 px-3 py-2.5"
                            >
                                <div className="flex min-w-0 flex-1 flex-col">
                                    <span className="truncate text-sm font-medium text-foreground">{row.nombre}</span>
                                    {row.grooming_insumo_id === null ? (
                                        <span className="text-[0.7rem] text-primary">{t('insumos.new_badge')}</span>
                                    ) : null}
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="text-xs text-muted-foreground">{moneda}</span>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        inputMode="decimal"
                                        value={row.precio}
                                        onChange={(e) => updatePrecio(index, e.target.value)}
                                        disabled={!canUpdate}
                                        className="w-28 tabular-nums"
                                        placeholder="0.00"
                                        aria-label={t('insumos.precio_label', { nombre: row.nombre })}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    disabled={!canUpdate}
                                    onClick={() => removeRow(index)}
                                    className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                                    aria-label={t('insumos.remove_label', { nombre: row.nombre })}
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
