import { Head, router, resetLayoutProps, setLayoutProps } from '@inertiajs/react';
import { Download, Wallet } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { AtencionDateRangeFilter } from '@/pages/clinica/historias-clinicas/components/atencion-date-range-filter';
import { formatMoney, formatNumber } from '@/pages/reportes/components/reporte-format';

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

const ALL = '__all__';

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
    const [loading, setLoading] = useState(false);
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

    const navigateWith = useCallback(
        (patch: Record<string, string | undefined>) => {
            setLoading(true);
            router.get(
                '/reportes/egresos',
                {
                    fecha_desde: patch.fecha_desde ?? filtros.fecha_desde,
                    fecha_hasta: patch.fecha_hasta ?? filtros.fecha_hasta,
                    sede_id:
                        patch.sede_id === ALL
                            ? undefined
                            : (patch.sede_id ?? filtros.sede_id ?? undefined),
                    motivo:
                        patch.motivo === ALL
                            ? undefined
                            : (patch.motivo ?? filtros.motivo ?? undefined),
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    onFinish: () => setLoading(false),
                },
            );
        },
        [filtros.fecha_desde, filtros.fecha_hasta, filtros.motivo, filtros.sede_id],
    );

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) {
            return items;
        }

        return items.filter((item) => {
            const haystack =
                `${item.sede_nombre ?? ''} ${item.motivo_label} ${item.notas ?? ''} ${item.registrado_por ?? ''}`.toLowerCase();

            return haystack.includes(q);
        });
    }, [items, search]);

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

    return (
        <>
            <Head title={t('title')} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('title')}
                    description={
                        <span className="inline-flex items-center gap-2">
                            <Wallet className="size-4 text-amber-600 dark:text-amber-400" />
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

                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={t('search_placeholder')}
                        className="h-10 w-full sm:max-w-xs"
                    />
                    <AtencionDateRangeFilter
                        desde={filtros.fecha_desde}
                        hasta={filtros.fecha_hasta}
                        defaultDesde={filtros.fecha_desde}
                        defaultHasta={filtros.fecha_hasta}
                        disabled={loading}
                        translationNs="reportes-egresos"
                        triggerClassName="h-10 min-w-[12rem]"
                        onApply={(desde, hasta) =>
                            navigateWith({ fecha_desde: desde, fecha_hasta: hasta })
                        }
                    />
                    <Select
                        value={filtros.sede_id ?? ALL}
                        onValueChange={(value) => navigateWith({ sede_id: value })}
                        disabled={loading}
                    >
                        <SelectTrigger className="h-10 w-full cursor-pointer sm:w-48">
                            <SelectValue placeholder={t('filters.sede')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>{t('filters.sede_todas')}</SelectItem>
                            {sedes.map((sede) => (
                                <SelectItem key={sede.id} value={sede.id}>
                                    {sede.nombre}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filtros.motivo ?? ALL}
                        onValueChange={(value) => navigateWith({ motivo: value })}
                        disabled={loading}
                    >
                        <SelectTrigger className="h-10 w-full cursor-pointer sm:w-56">
                            <SelectValue placeholder={t('filters.motivo')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>{t('filters.motivo_todos')}</SelectItem>
                            {motivos.map((m) => (
                                <SelectItem key={m.value} value={m.value}>
                                    {m.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border border-border/70 bg-card px-4 py-3">
                        <p className="text-xs font-medium text-muted-foreground">
                            {t('kpis.cantidad')}
                        </p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums">
                            {formatNumber(filteredTotales.cantidad, locale, 0)}
                        </p>
                    </div>
                    <div className="rounded-xl border border-border/70 bg-card px-4 py-3 sm:col-span-1 lg:col-span-1">
                        <p className="text-xs font-medium text-muted-foreground">
                            {t('kpis.monto')}
                        </p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums text-amber-700 dark:text-amber-400">
                            {formatMoney(filteredTotales.monto, moneda, locale)}
                        </p>
                    </div>
                    {por_motivo.slice(0, 2).map((slice) => (
                        <div
                            key={slice.motivo}
                            className="rounded-xl border border-border/70 bg-muted/20 px-4 py-3"
                        >
                            <p className="truncate text-xs font-medium text-muted-foreground">
                                {slice.motivo_label}
                            </p>
                            <p className="mt-1 text-lg font-semibold tabular-nums">
                                {formatMoney(slice.monto, moneda, locale)}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {t('kpis.movimientos', { count: slice.cantidad })}
                            </p>
                        </div>
                    ))}
                </div>

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
                                        {t('columns.fecha')}
                                    </th>
                                    <th className="px-3 py-2 text-left font-medium">
                                        {t('columns.sede')}
                                    </th>
                                    <th className="px-3 py-2 text-left font-medium">
                                        {t('columns.motivo')}
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        {t('columns.monto')}
                                    </th>
                                    <th className="px-3 py-2 text-left font-medium">
                                        {t('columns.notas')}
                                    </th>
                                    <th className="px-3 py-2 text-left font-medium">
                                        {t('columns.registrado_por')}
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
                                        <td
                                            className={cn(
                                                'px-3 py-2.5 text-right font-medium tabular-nums text-amber-700 dark:text-amber-400',
                                            )}
                                        >
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
