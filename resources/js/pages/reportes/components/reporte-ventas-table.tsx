import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { formatMoney, formatNumber, formatPct } from './reporte-format';
import type { ReporteVentasItem, SortDir, SortKey } from './types';

type Props = {
    items: ReporteVentasItem[];
    moneda: string;
    locale: string;
    showTipo?: boolean;
    emptyLabel: string;
};

function compareNullable(a: number | null, b: number | null, dir: SortDir): number {
    if (a === null && b === null) {
        return 0;
    }
    if (a === null) {
        return 1;
    }
    if (b === null) {
        return -1;
    }

    return dir === 'asc' ? a - b : b - a;
}

export function ReporteVentasTable({ items, moneda, locale, showTipo = false, emptyLabel }: Props) {
    const { t } = useTranslation('reportes-ventas');
    const [sortKey, setSortKey] = useState<SortKey>('cantidad');
    const [sortDir, setSortDir] = useState<SortDir>('desc');

    const sorted = useMemo(() => {
        const copy = [...items];
        copy.sort((a, b) => {
            switch (sortKey) {
                case 'nombre':
                case 'categoria':
                case 'tipo': {
                    const av = (a[sortKey] ?? '').toString();
                    const bv = (b[sortKey] ?? '').toString();
                    const cmp = av.localeCompare(bv, locale, { sensitivity: 'base' });

                    return sortDir === 'asc' ? cmp : -cmp;
                }
                case 'cantidad':
                case 'ventas':
                case 'ingreso':
                    return sortDir === 'asc' ? a[sortKey] - b[sortKey] : b[sortKey] - a[sortKey];
                case 'precio_unit':
                case 'costo_unit':
                case 'costo':
                case 'utilidad':
                case 'margen_pct':
                    return compareNullable(a[sortKey], b[sortKey], sortDir);
                default:
                    return 0;
            }
        });

        return copy;
    }, [items, locale, sortDir, sortKey]);

    const toggleSort = (key: SortKey) => {
        if (sortKey === key) {
            setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));

            return;
        }
        setSortKey(key);
        setSortDir(key === 'nombre' || key === 'categoria' || key === 'tipo' ? 'asc' : 'desc');
    };

    const SortButton = ({ column, label }: { column: SortKey; label: string }) => {
        const active = sortKey === column;
        const Icon = !active ? ArrowUpDown : sortDir === 'asc' ? ArrowUp : ArrowDown;

        return (
            <Button
                type="button"
                variant="ghost"
                size="sm"
                className={cn('-ml-2 h-8 gap-1 px-2 font-medium', active && 'text-foreground')}
                onClick={() => toggleSort(column)}
            >
                {label}
                <Icon className="size-3.5 opacity-70" />
            </Button>
        );
    };

    if (items.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-border/70 bg-muted/20 px-6 py-12 text-center text-sm text-muted-foreground">
                {emptyLabel}
            </div>
        );
    }

    return (
        <div className="overflow-x-auto rounded-xl border border-border/70">
            <table className="w-full min-w-5xl border-collapse text-sm">
                <thead>
                    <tr className="border-b border-border/70 bg-muted/40">
                        <th className="px-3 py-2 text-left font-medium">
                            <SortButton column="nombre" label={t('common.columns.nombre')} />
                        </th>
                        <th className="px-3 py-2 text-left font-medium">
                            <SortButton column="categoria" label={t('common.columns.categoria')} />
                        </th>
                        {showTipo ? (
                            <th className="px-3 py-2 text-left font-medium">
                                <SortButton column="tipo" label={t('common.columns.tipo')} />
                            </th>
                        ) : null}
                        <th className="px-3 py-2 text-right font-medium">
                            <SortButton column="cantidad" label={t('common.columns.cantidad')} />
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            <SortButton column="ventas" label={t('common.columns.ventas')} />
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            <SortButton column="precio_unit" label={t('common.columns.precio')} />
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            <SortButton column="costo_unit" label={t('common.columns.costo')} />
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            <SortButton column="ingreso" label={t('common.columns.ingreso')} />
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            <SortButton column="costo" label={t('common.columns.costo_total')} />
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            <SortButton column="utilidad" label={t('common.columns.utilidad')} />
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            <SortButton column="margen_pct" label={t('common.columns.margen')} />
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {sorted.map((row) => (
                        <tr
                            key={`${row.tipo}-${row.id}`}
                            className={cn(
                                'border-b border-border/50 last:border-b-0',
                                !row.tiene_costo ? 'bg-amber-500/5' : 'odd:bg-background even:bg-muted/20',
                            )}
                        >
                            <td className="max-w-[16rem] px-3 py-2.5 font-medium">
                                <span className="line-clamp-2">{row.nombre}</span>
                            </td>
                            <td className="px-3 py-2.5 text-muted-foreground">
                                {row.categoria ?? t('common.na')}
                            </td>
                            {showTipo ? (
                                <td className="px-3 py-2.5">
                                    {t(`common.tipos.${row.tipo}`, { defaultValue: row.tipo })}
                                </td>
                            ) : null}
                            <td className="px-3 py-2.5 text-right tabular-nums">
                                {formatNumber(row.cantidad, locale)}
                            </td>
                            <td className="px-3 py-2.5 text-right tabular-nums">
                                {formatNumber(row.ventas, locale, 0)}
                            </td>
                            <td className="px-3 py-2.5 text-right tabular-nums">
                                {formatMoney(row.precio_unit, moneda, locale)}
                            </td>
                            <td className="px-3 py-2.5 text-right tabular-nums">
                                {formatMoney(row.costo_unit, moneda, locale)}
                            </td>
                            <td className="px-3 py-2.5 text-right tabular-nums">
                                {formatMoney(row.ingreso, moneda, locale)}
                            </td>
                            <td className="px-3 py-2.5 text-right tabular-nums">
                                {formatMoney(row.costo, moneda, locale)}
                            </td>
                            <td
                                className={cn(
                                    'px-3 py-2.5 text-right font-medium tabular-nums',
                                    row.utilidad !== null && row.utilidad < 0
                                        ? 'text-rose-600 dark:text-rose-400'
                                        : row.utilidad !== null
                                          ? 'text-emerald-700 dark:text-emerald-400'
                                          : 'text-muted-foreground',
                                )}
                            >
                                {formatMoney(row.utilidad, moneda, locale)}
                            </td>
                            <td className="px-3 py-2.5 text-right tabular-nums">
                                {formatPct(row.margen_pct, locale)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
