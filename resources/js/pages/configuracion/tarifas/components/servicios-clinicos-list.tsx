import { Clock3, Pencil, Plus, Stethoscope, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { CatalogoClinicaRow } from '../types';
import { TarifaRowActions } from './tarifa-row-actions';

function formatPrecio(amount: string, moneda: string) {
    const n = Number(amount);
    const cur = moneda === 'USD' ? 'USD' : 'PEN';

    return Number.isNaN(n)
        ? amount
        : new Intl.NumberFormat(undefined, { style: 'currency', currency: cur }).format(n);
}

type Props = {
    rows: readonly CatalogoClinicaRow[];
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    onCreate: () => void;
    onEdit: (row: CatalogoClinicaRow) => void;
    onDelete: (row: CatalogoClinicaRow) => void;
};

export function ServiciosClinicosList({
    rows,
    canCreate,
    canUpdate,
    canDelete,
    onCreate,
    onEdit,
    onDelete,
}: Props) {
    const { t } = useTranslation(['tarifas-servicios', 'common']);
    const [categoriaFilter, setCategoriaFilter] = useState<string | null>(null);

    const categorias = useMemo(() => {
        const map = new Map<string, number>();
        for (const row of rows) {
            const key = row.categoria?.trim() || '';
            if (key === '') {
                continue;
            }
            map.set(key, (map.get(key) ?? 0) + 1);
        }

        return [...map.entries()].sort((a, b) => a[0].localeCompare(b[0], 'es'));
    }, [rows]);

    const filtered = useMemo(() => {
        if (!categoriaFilter) {
            return rows;
        }

        return rows.filter((row) => (row.categoria ?? '') === categoriaFilter);
    }, [rows, categoriaFilter]);

    if (rows.length === 0) {
        return (
            <div className="flex flex-col items-center gap-4 px-4 py-12 text-center sm:px-6">
                <div className="flex size-14 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-700 dark:text-sky-300">
                    <Stethoscope className="size-7" strokeWidth={1.75} />
                </div>
                <div className="max-w-sm space-y-1.5">
                    <p className="text-sm font-semibold text-foreground">{t('catalogo.empty_clinica_title')}</p>
                    <p className="text-sm text-muted-foreground">{t('catalogo.empty_clinica')}</p>
                </div>
                {canCreate ? (
                    <Button type="button" className="cursor-pointer gap-2" onClick={onCreate}>
                        <Plus className="size-4" strokeWidth={2.5} />
                        {t('catalogo.add_clinica')}
                    </Button>
                ) : null}
            </div>
        );
    }

    return (
        <div className="space-y-4 px-4 py-4 sm:px-6 sm:py-5">
            {categorias.length > 0 ? (
                <div className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 scrollbar-none">
                    <button
                        type="button"
                        onClick={() => setCategoriaFilter(null)}
                        className={cn(
                            'shrink-0 cursor-pointer rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                            categoriaFilter === null
                                ? 'border-sky-600/30 bg-sky-500/15 text-sky-900 dark:text-sky-100'
                                : 'border-border/60 bg-background text-muted-foreground hover:bg-muted/50',
                        )}
                    >
                        {t('catalogo.filter_all')} ({rows.length})
                    </button>
                    {categorias.map(([nombre, count]) => (
                        <button
                            key={nombre}
                            type="button"
                            onClick={() => setCategoriaFilter(nombre)}
                            className={cn(
                                'shrink-0 cursor-pointer rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                                categoriaFilter === nombre
                                    ? 'border-sky-600/30 bg-sky-500/15 text-sky-900 dark:text-sky-100'
                                    : 'border-border/60 bg-background text-muted-foreground hover:bg-muted/50',
                            )}
                        >
                            {nombre} ({count})
                        </button>
                    ))}
                </div>
            ) : null}

            <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {filtered.map((row) => (
                    <li key={row.id}>
                        <article
                            className={cn(
                                'flex h-full flex-col gap-3 rounded-2xl border border-border/60 bg-card p-4 shadow-sm transition-colors',
                                !row.activo && 'opacity-70',
                            )}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 space-y-1.5">
                                    {row.categoria ? (
                                        <Badge
                                            variant="outline"
                                            className="max-w-full truncate border-sky-500/25 bg-sky-500/8 text-[0.65rem] font-medium text-sky-800 dark:text-sky-200"
                                        >
                                            {row.categoria}
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline" className="text-[0.65rem] font-normal text-muted-foreground">
                                            {t('catalogo.sin_categoria')}
                                        </Badge>
                                    )}
                                    <h3 className="text-sm font-semibold leading-snug text-foreground">{row.nombre}</h3>
                                </div>
                                {(canUpdate || canDelete) && (
                                    <div className="shrink-0">
                                        <TarifaRowActions
                                            canUpdate={canUpdate}
                                            canDelete={canDelete}
                                            onEdit={() => onEdit(row)}
                                            onDelete={() => onDelete(row)}
                                        />
                                    </div>
                                )}
                            </div>

                            <div className="mt-auto space-y-2 border-t border-border/50 pt-3">
                                <div className="flex flex-wrap items-end justify-between gap-2">
                                    <div>
                                        <p className="text-[10px] uppercase tracking-wide text-muted-foreground">
                                            {t('columns.precio')}
                                        </p>
                                        <p className="text-base font-semibold tabular-nums text-foreground">
                                            {formatPrecio(row.precio_lista, row.moneda)}
                                        </p>
                                    </div>
                                    <div className="flex flex-col items-end gap-1.5">
                                        {row.duracion_minutos != null ? (
                                            <span className="inline-flex items-center gap-1 text-[11px] text-muted-foreground">
                                                <Clock3 className="size-3" />
                                                {row.duracion_minutos} min
                                            </span>
                                        ) : null}
                                        <span
                                            className={cn(
                                                'inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium',
                                                row.activo
                                                    ? 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-400'
                                                    : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {row.activo ? t('common:filters.active') : t('common:filters.inactive')}
                                        </span>
                                    </div>
                                </div>
                                {row.precio_costo != null && row.precio_costo !== '' ? (
                                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                                        <span className="text-muted-foreground">
                                            {t('columns.precio_costo')}:{' '}
                                            <span className="font-medium tabular-nums text-foreground">
                                                {formatPrecio(row.precio_costo, row.moneda)}
                                            </span>
                                        </span>
                                        {(() => {
                                            const lista = Number(row.precio_lista);
                                            const costo = Number(row.precio_costo);
                                            if (!Number.isFinite(lista) || lista <= 0 || !Number.isFinite(costo)) {
                                                return null;
                                            }
                                            const margen = ((lista - costo) / lista) * 100;
                                            return (
                                                <span className="font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">
                                                    {t('columns.margen')}: {margen.toFixed(1)}%
                                                </span>
                                            );
                                        })()}
                                    </div>
                                ) : null}
                            </div>

                            {/* Touch-friendly primary action on small screens */}
                            {canUpdate ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="mt-1 w-full cursor-pointer gap-2 sm:hidden"
                                    onClick={() => onEdit(row)}
                                >
                                    <Pencil className="size-3.5" />
                                    {t('actions.editar')}
                                </Button>
                            ) : null}
                            {canDelete && !canUpdate ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="mt-1 w-full cursor-pointer gap-2 text-destructive sm:hidden"
                                    onClick={() => onDelete(row)}
                                >
                                    <Trash2 className="size-3.5" />
                                    {t('actions.eliminar')}
                                </Button>
                            ) : null}
                        </article>
                    </li>
                ))}
            </ul>

            {filtered.length === 0 ? (
                <p className="py-8 text-center text-sm text-muted-foreground">{t('catalogo.filter_empty')}</p>
            ) : null}
        </div>
    );
}
