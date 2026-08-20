import { Head, router, resetLayoutProps, setLayoutProps } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Coins,
    Download,
    Layers,
    Wallet,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FilterChips, PageHeader, type FilterChip } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { formatMoney, formatNumber } from '@/pages/reportes/components/reporte-format';
import { ReporteVentasFilters } from '@/pages/reportes/components/reporte-ventas-filters';

type SedeOpcion = { id: string; nombre: string };
type MotivoOpcion = { value: string; label: string };

type Filtros = {
    fecha_desde: string;
    fecha_hasta: string;
    periodo: string;
    sede_id: string | null;
    motivo: string | null;
};

type Totales = {
    cantidad: number;
    monto: number;
};

type PorMotivo = {
    motivo: string;
    motivo_label: string;
    cantidad: number;
    monto: number;
};

type EgresoItem = {
    id: string;
    fecha: string | null;
    sede_id: string | null;
    sede_nombre: string | null;
    motivo: string;
    motivo_label: string;
    monto: number;
    notas: string | null;
    caja_sesion_id: string;
    registrado_por: string | null;
};

type Props = {
    moneda: string;
    filtros: Filtros;
    totales: Totales;
    por_motivo: PorMotivo[];
    items: EgresoItem[];
    sedes: SedeOpcion[];
    motivos: MotivoOpcion[];
    can_export?: boolean;
};

type SortKey = 'fecha' | 'sede_nombre' | 'motivo_label' | 'monto' | 'registrado_por';
type SortDir = 'asc' | 'desc';

function formatFecha(iso: string | null, locale: string): string {
    if (!iso) {
        return '—';
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return '—';
    }
    try {
        return new Intl.DateTimeFormat(locale, {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(d);
    } catch {
        return iso;
    }
}

export default function ReportesEgresosIndex({
    moneda,
    filtros,
    totales,
    por_motivo,
    items,
    sedes,
    motivos,
    can_export,
}: Props) {
    const { t, i18n } = useTranslation(['reportes-egresos', 'common']);
    const { can } = usePermission();
    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';
    const [search, setSearch] = useState('');
    const [sortKey, setSortKey] = useState<SortKey>('fecha');
    const [sortDir, setSortDir] = useState<SortDir>('desc');
    const canExport = can_export ?? can('reporte-financiero.export');

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('common.reportes'), href: '#' },
                { title: t('title'), href: '/reportes/egresos' },
            ],
        });

        return () => {
            resetLayoutProps();
        };
    }, [t]);

    const navigateFilter = useCallback(
        (patch: { sede_id?: string; motivo?: string }) => {
            router.get(
                '/reportes/egresos',
                {
                    fecha_desde: filtros.fecha_desde,
                    fecha_hasta: filtros.fecha_hasta,
                    sede_id:
                        patch.sede_id !== undefined
                            ? patch.sede_id || undefined
                            : (filtros.sede_id ?? undefined),
                    motivo:
                        patch.motivo !== undefined
                            ? patch.motivo || undefined
                            : (filtros.motivo ?? undefined),
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        },
        [filtros.fecha_desde, filtros.fecha_hasta, filtros.motivo, filtros.sede_id],
    );

    const sedeOptions = useMemo<FilterChip<string>[]>(
        () => [
            { value: 'all', label: t('filters.sede_todas'), tone: 'muted' },
            ...sedes.map((s) => ({
                value: s.id,
                label: s.nombre,
                tone: 'info' as const,
            })),
        ],
        [sedes, t],
    );

    const motivoOptions = useMemo<FilterChip<string>[]>(
        () => [
            { value: 'all', label: t('filters.motivo_todos'), tone: 'muted' },
            ...motivos.map((m) => ({
                value: m.value,
                label: m.label,
                tone: 'default' as const,
            })),
        ],
        [motivos, t],
    );

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        let rows = items;
        if (q) {
            rows = rows.filter((item) => {
                const haystack =
                    `${item.sede_nombre ?? ''} ${item.motivo_label} ${item.notas ?? ''} ${item.registrado_por ?? ''}`.toLowerCase();

                return haystack.includes(q);
            });
        }

        const copy = [...rows];
        copy.sort((a, b) => {
            if (sortKey === 'monto') {
                return sortDir === 'asc' ? a.monto - b.monto : b.monto - a.monto;
            }
            if (sortKey === 'fecha') {
                const av = a.fecha ?? '';
                const bv = b.fecha ?? '';
                const cmp = av.localeCompare(bv);

                return sortDir === 'asc' ? cmp : -cmp;
            }
            const av = (a[sortKey] ?? '').toString();
            const bv = (b[sortKey] ?? '').toString();
            const cmp = av.localeCompare(bv, locale, { sensitivity: 'base' });

            return sortDir === 'asc' ? cmp : -cmp;
        });

        return copy;
    }, [items, locale, search, sortDir, sortKey]);

    const filteredTotales = useMemo(() => {
        if (!search.trim()) {
            return totales;
        }
        const monto = filtered.reduce((acc, row) => acc + row.monto, 0);

        return { cantidad: filtered.length, monto: Math.round(monto * 100) / 100 };
    }, [filtered, search, totales]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        params.set('fecha_desde', filtros.fecha_desde);
        params.set('fecha_hasta', filtros.fecha_hasta);
        if (filtros.sede_id) {
            params.set('sede_id', filtros.sede_id);
        }
        if (filtros.motivo) {
            params.set('motivo', filtros.motivo);
        }
        if (search.trim()) {
            params.set('search', search.trim());
        }

        return `/reportes/egresos/export?${params.toString()}`;
    }, [filtros.fecha_desde, filtros.fecha_hasta, filtros.motivo, filtros.sede_id, search]);

    const toggleSort = (key: SortKey) => {
        if (sortKey === key) {
            setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));

            return;
        }
        setSortKey(key);
        setSortDir(key === 'fecha' || key === 'monto' ? 'desc' : 'asc');
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

    const kpiCards = [
        {
            key: 'cantidad',
            label: t('kpis.cantidad'),
            value: formatNumber(filteredTotales.cantidad, locale, 0),
            icon: Layers,
            tone: 'text-sky-600 dark:text-sky-400',
        },
        {
            key: 'monto',
            label: t('kpis.monto'),
            value: formatMoney(filteredTotales.monto, moneda, locale),
            icon: Wallet,
            tone: 'text-amber-600 dark:text-amber-400',
        },
        {
            key: 'motivos',
            label: t('kpis.motivos_distintos'),
            value: formatNumber(por_motivo.length, locale, 0),
            icon: Coins,
            tone: 'text-violet-600 dark:text-violet-400',
        },
    ];

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('title')}
                    description={
                        <span className="inline-flex items-center gap-2">
                            <Wallet className="size-4 text-emerald-600 dark:text-emerald-400" />
                            {t('description')}
                        </span>
                    }
                    action={
                        canExport ? (
                            <Button asChild variant="outline" className="cursor-pointer gap-2">
                                <a href={exportUrl} download>
                                    <Download className="size-4" strokeWidth={2.5} />
                                    <span className="hidden sm:inline">
                                        {t('common:actions.export_xlsx')}
                                    </span>
                                </a>
                            </Button>
                        ) : null
                    }
                />

                <ReporteVentasFilters
                    url="/reportes/egresos"
                    filtros={filtros}
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder={t('search_placeholder')}
                    translationNs="reportes-egresos"
                    hint={t('hint')}
                    extraQuery={{
                        sede_id: filtros.sede_id ?? undefined,
                        motivo: filtros.motivo ?? undefined,
                    }}
                >
                    {sedes.length > 0 ? (
                        <FilterChips
                            ariaLabel={t('filters.sede')}
                            value={filtros.sede_id ?? 'all'}
                            onChange={(v) => navigateFilter({ sede_id: v === 'all' ? '' : v })}
                            options={sedeOptions}
                            className="sm:min-w-56"
                        />
                    ) : null}
                    <FilterChips
                        ariaLabel={t('filters.motivo')}
                        value={filtros.motivo ?? 'all'}
                        onChange={(v) => navigateFilter({ motivo: v === 'all' ? '' : v })}
                        options={motivoOptions}
                        className="sm:min-w-56"
                    />
                </ReporteVentasFilters>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {kpiCards.map((card) => (
                        <div
                            key={card.key}
                            className="rounded-xl border border-border/70 bg-card px-4 py-3 shadow-sm"
                        >
                            <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                                <card.icon className={cn('size-3.5', card.tone)} />
                                {card.label}
                            </div>
                            <p className={cn('mt-1.5 text-lg font-semibold tracking-tight', card.tone)}>
                                {card.value}
                            </p>
                        </div>
                    ))}
                </div>

                {por_motivo.length > 0 ? (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {por_motivo.map((slice) => (
                            <div
                                key={slice.motivo}
                                className="rounded-xl border border-border/70 bg-card p-4 shadow-sm"
                            >
                                <h3 className="font-semibold tracking-tight">{slice.motivo_label}</h3>
                                <dl className="mt-3 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt className="text-muted-foreground">{t('kpis.cantidad')}</dt>
                                        <dd className="font-medium tabular-nums">
                                            {formatNumber(slice.cantidad, locale, 0)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">{t('kpis.monto')}</dt>
                                        <dd className="font-medium tabular-nums text-amber-700 dark:text-amber-400">
                                            {formatMoney(slice.monto, moneda, locale)}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        ))}
                    </div>
                ) : null}

                {filtered.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border/70 bg-muted/20 px-6 py-12 text-center text-sm text-muted-foreground">
                        {t('sin_datos')}
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-border/70">
                        <table className="w-full min-w-4xl border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-border/70 bg-muted/40">
                                    <th className="px-3 py-2 text-left font-medium">
                                        <SortButton column="fecha" label={t('columns.fecha')} />
                                    </th>
                                    <th className="px-3 py-2 text-left font-medium">
                                        <SortButton column="sede_nombre" label={t('columns.sede')} />
                                    </th>
                                    <th className="px-3 py-2 text-left font-medium">
                                        <SortButton
                                            column="motivo_label"
                                            label={t('columns.motivo')}
                                        />
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        <SortButton column="monto" label={t('columns.monto')} />
                                    </th>
                                    <th className="px-3 py-2 text-left font-medium">
                                        {t('columns.notas')}
                                    </th>
                                    <th className="px-3 py-2 text-left font-medium">
                                        <SortButton
                                            column="registrado_por"
                                            label={t('columns.registrado_por')}
                                        />
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border/50 odd:bg-background even:bg-muted/20 last:border-b-0"
                                    >
                                        <td className="whitespace-nowrap px-3 py-2.5 tabular-nums text-muted-foreground">
                                            {formatFecha(row.fecha, locale)}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            {row.sede_nombre ?? t('common.na')}
                                        </td>
                                        <td className="px-3 py-2.5 font-medium">
                                            {row.motivo_label}
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-medium tabular-nums text-amber-700 dark:text-amber-400">
                                            {formatMoney(row.monto, moneda, locale)}
                                        </td>
                                        <td className="max-w-[16rem] px-3 py-2.5 text-muted-foreground">
                                            <span className="line-clamp-2">
                                                {row.notas ?? t('common.na')}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2.5 text-muted-foreground">
                                            {row.registrado_por ?? t('common.na')}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}
