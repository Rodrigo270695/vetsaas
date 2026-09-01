import { Head, Link, router, resetLayoutProps, setLayoutProps } from '@inertiajs/react';
import { Banknote, Coins, CreditCard, Download, FileText, Landmark, Receipt, Smartphone, Ticket } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { ReportCheckboxSelect } from '@/pages/reportes/components/report-checkbox-select';
import { formatMoney } from '@/pages/reportes/components/reporte-format';
import { ReporteVentasFilters } from '@/pages/reportes/components/reporte-ventas-filters';

type Slice = { ventas: number; ingresos: number };

type Filtros = {
    fecha_desde: string;
    fecha_hasta: string;
    periodo: string;
    tipos: string[];
    metodos: string[];
};

type Item = {
    id: string;
    fecha: string | null;
    numero: string;
    comprobante: string;
    tipo: string;
    cliente: string;
    metodo_pago: string;
    metodos: string[];
    metodos_label: string;
    total: number;
    fel_estado: string;
};

type Props = {
    moneda: string;
    filtros: Filtros;
    totales: { ventas: number; ingresos: number };
    por_tipo: Record<string, Slice>;
    por_metodo: Record<string, Slice>;
    items: Item[];
    can_export?: boolean;
};

const TIPO_KEYS = ['ticket', 'boleta', 'factura'] as const;
const METODO_KEYS = ['efectivo', 'yape', 'plin', 'tarjeta', 'transferencia'] as const;

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

function tipoBadgeClass(tipo: string): string {
    switch (tipo) {
        case 'factura':
            return 'border-sky-500/40 bg-sky-500/10 text-sky-800 dark:text-sky-200';
        case 'boleta':
            return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200';
        default:
            return 'border-amber-500/40 bg-amber-500/10 text-amber-900 dark:text-amber-200';
    }
}

export default function IngresosVentasIndex({
    moneda,
    filtros,
    totales,
    por_tipo,
    por_metodo,
    items,
    can_export,
}: Props) {
    const { t, i18n } = useTranslation(['reportes-ventas', 'common']);
    const { can } = usePermission();
    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';
    const [search, setSearch] = useState('');
    const canExport = can_export ?? can('reporte-financiero.export');

    const tipoOptions = [
        { value: 'ticket', label: t('ingresos.tipos.ticket'), icon: Ticket, tone: 'warning' as const },
        { value: 'boleta', label: t('ingresos.tipos.boleta'), icon: Receipt, tone: 'success' as const },
        { value: 'factura', label: t('ingresos.tipos.factura'), icon: FileText, tone: 'info' as const },
    ];
    const metodoOptions = [
        { value: 'efectivo', label: t('ingresos.metodos.efectivo'), icon: Banknote, tone: 'success' as const },
        { value: 'yape', label: t('ingresos.metodos.yape'), icon: Smartphone, tone: 'default' as const },
        { value: 'plin', label: t('ingresos.metodos.plin'), icon: Smartphone, tone: 'info' as const },
        { value: 'tarjeta', label: t('ingresos.metodos.tarjeta'), icon: CreditCard, tone: 'default' as const },
        { value: 'transferencia', label: t('ingresos.metodos.transferencia'), icon: Landmark, tone: 'muted' as const },
    ];

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('common.reportes'), href: '#' },
                { title: t('ingresos.title'), href: '/reportes/ingresos-ventas' },
            ],
        });

        return () => {
            resetLayoutProps();
        };
    }, [t]);

    const extraQuery = useMemo(() => {
        const q: Record<string, string | undefined> = {};
        const allTipos = TIPO_KEYS.every((k) => filtros.tipos.includes(k));
        const allMetodos = METODO_KEYS.every((k) => filtros.metodos.includes(k));
        if (!allTipos) {
            q.tipos = filtros.tipos.join(',');
        }
        if (!allMetodos) {
            q.metodos = filtros.metodos.join(',');
        }

        return q;
    }, [filtros.tipos, filtros.metodos]);

    const applyFilters = useCallback(
        (next: { tipos?: string[]; metodos?: string[]; desde?: string; hasta?: string }) => {
            const tipos = next.tipos ?? filtros.tipos;
            const metodos = next.metodos ?? filtros.metodos;
            const allTipos = TIPO_KEYS.every((k) => tipos.includes(k));
            const allMetodos = METODO_KEYS.every((k) => metodos.includes(k));

            router.get(
                '/reportes/ingresos-ventas',
                {
                    fecha_desde: next.desde ?? filtros.fecha_desde,
                    fecha_hasta: next.hasta ?? filtros.fecha_hasta,
                    ...(allTipos ? {} : { tipos: tipos.join(',') }),
                    ...(allMetodos ? {} : { metodos: metodos.join(',') }),
                },
                { preserveScroll: true, preserveState: true, replace: true },
            );
        },
        [filtros],
    );

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) {
            return items;
        }

        return items.filter((item) => {
            const haystack =
                `${item.numero} ${item.comprobante} ${item.cliente} ${item.metodos_label}`.toLowerCase();

            return haystack.includes(q);
        });
    }, [items, search]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        params.set('fecha_desde', filtros.fecha_desde);
        params.set('fecha_hasta', filtros.fecha_hasta);
        if (extraQuery.tipos) {
            params.set('tipos', extraQuery.tipos);
        }
        if (extraQuery.metodos) {
            params.set('metodos', extraQuery.metodos);
        }
        if (search.trim()) {
            params.set('search', search.trim());
        }

        return `/reportes/ingresos-ventas/export?${params.toString()}`;
    }, [extraQuery.metodos, extraQuery.tipos, filtros.fecha_desde, filtros.fecha_hasta, search]);

    return (
        <>
            <Head title={t('ingresos.title')} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('ingresos.title')}
                    description={
                        <span className="inline-flex items-center gap-2">
                            <Banknote className="size-4 text-emerald-600 dark:text-emerald-400" />
                            {t('ingresos.description')}
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
                    url="/reportes/ingresos-ventas"
                    filtros={filtros}
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder={t('ingresos.search_placeholder')}
                    extraQuery={extraQuery}
                    hint={t('ingresos.hint')}
                >
                    <ReportCheckboxSelect
                        label={t('ingresos.filter_tipos')}
                        allLabel={t('ingresos.todos_comprobantes')}
                        options={tipoOptions}
                        selected={filtros.tipos}
                        onChange={(tipos) => applyFilters({ tipos })}
                    />
                    <ReportCheckboxSelect
                        label={t('ingresos.filter_metodos')}
                        allLabel={t('ingresos.todos_metodos')}
                        options={metodoOptions}
                        selected={filtros.metodos}
                        onChange={(metodos) => applyFilters({ metodos })}
                    />
                </ReporteVentasFilters>

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-xl border border-border/70 bg-card px-4 py-3 shadow-sm">
                        <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                            <Receipt className="size-3.5 text-violet-600 dark:text-violet-400" />
                            {t('ingresos.kpis.ventas')}
                        </div>
                        <p className="mt-1.5 text-lg font-semibold tracking-tight">
                            {totales.ventas}
                        </p>
                    </div>
                    <div className="rounded-xl border border-border/70 bg-card px-4 py-3 shadow-sm">
                        <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                            <Coins className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                            {t('ingresos.kpis.ingresos')}
                        </div>
                        <p className="mt-1.5 text-lg font-semibold tracking-tight text-emerald-700 dark:text-emerald-400">
                            {formatMoney(totales.ingresos, moneda, locale)}
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-xl border border-border/70 bg-card p-4 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            {t('ingresos.por_tipo')}
                        </p>
                        <ul className="mt-3 space-y-2">
                            {TIPO_KEYS.map((key) => {
                                const slice = por_tipo[key] ?? { ventas: 0, ingresos: 0 };

                                return (
                                    <li
                                        key={key}
                                        className="flex items-center justify-between gap-3 text-sm"
                                    >
                                        <Badge variant="outline" className={cn(tipoBadgeClass(key))}>
                                            {t(`ingresos.tipos.${key}`)}
                                        </Badge>
                                        <span className="tabular-nums text-muted-foreground">
                                            {slice.ventas} · {formatMoney(slice.ingresos, moneda, locale)}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                    <div className="rounded-xl border border-border/70 bg-card p-4 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            {t('ingresos.por_metodo')}
                        </p>
                        <ul className="mt-3 space-y-2">
                            {METODO_KEYS.map((key) => {
                                const slice = por_metodo[key] ?? { ventas: 0, ingresos: 0 };

                                return (
                                    <li
                                        key={key}
                                        className="flex items-center justify-between gap-3 text-sm"
                                    >
                                        <span>{t(`ingresos.metodos.${key}`)}</span>
                                        <span className="tabular-nums text-muted-foreground">
                                            {slice.ventas} · {formatMoney(slice.ingresos, moneda, locale)}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </div>

                {items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t('common.sin_datos')}</p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-border/70">
                        <table className="w-full min-w-208 text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 font-medium">{t('ingresos.columns.fecha')}</th>
                                    <th className="px-3 py-2 font-medium">{t('ingresos.columns.numero')}</th>
                                    <th className="px-3 py-2 font-medium">{t('ingresos.columns.comprobante')}</th>
                                    <th className="px-3 py-2 font-medium">{t('ingresos.columns.tipo')}</th>
                                    <th className="px-3 py-2 font-medium">{t('ingresos.columns.cliente')}</th>
                                    <th className="px-3 py-2 font-medium">{t('ingresos.columns.metodo')}</th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        {t('ingresos.columns.total')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.map((item) => (
                                    <tr key={item.id} className="border-b border-border/50 last:border-0">
                                        <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                                            {formatFecha(item.fecha, locale)}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Link
                                                href={`/caja/ventas/${item.id}`}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {item.numero}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs">{item.comprobante}</td>
                                        <td className="px-3 py-2">
                                            <Badge
                                                variant="outline"
                                                className={cn(tipoBadgeClass(item.tipo))}
                                            >
                                                {t(`ingresos.tipos.${item.tipo}`)}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2">{item.cliente}</td>
                                        <td className="px-3 py-2 text-xs">{item.metodos_label}</td>
                                        <td className="px-3 py-2 text-right font-medium tabular-nums">
                                            {formatMoney(item.total, moneda, locale)}
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
