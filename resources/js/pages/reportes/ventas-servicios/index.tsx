import { Head, router, setLayoutProps, resetLayoutProps } from '@inertiajs/react';
import { AlertTriangle, Scissors, Stethoscope, Syringe } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { formatMoney, formatNumber, formatPct } from '@/pages/reportes/components/reporte-format';
import { ReporteVentasFilters } from '@/pages/reportes/components/reporte-ventas-filters';
import { ReporteVentasKpis } from '@/pages/reportes/components/reporte-ventas-kpis';
import { ReporteVentasTable } from '@/pages/reportes/components/reporte-ventas-table';
import type {
    ReporteServicioResumen,
    ReporteServicioResumenSlice,
    ReporteVentasFiltros,
    ReporteVentasItem,
    ReporteVentasTotales,
} from '@/pages/reportes/components/types';

type Capabilities = {
    ventas: boolean;
    grooming: boolean;
};

type Props = {
    moneda: string;
    filtros: ReporteVentasFiltros;
    totales: ReporteVentasTotales;
    resumen: ReporteServicioResumen;
    items: ReporteVentasItem[];
    capabilities: Capabilities;
};

type TabValue = 'resumen' | 'todos' | 'tratamiento' | 'vacuna' | 'grooming';

function ResumenCard({
    title,
    icon: Icon,
    slice,
    moneda,
    locale,
    tone,
    onOpen,
    actionLabel,
}: {
    title: string;
    icon: typeof Stethoscope;
    slice: ReporteServicioResumenSlice;
    moneda: string;
    locale: string;
    tone: string;
    onOpen: () => void;
    actionLabel: string;
}) {
    const { t } = useTranslation('reportes-ventas');

    return (
        <div className="rounded-xl border border-border/70 bg-card p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Icon className={cn('size-5', tone)} />
                    <h3 className="font-semibold tracking-tight">{title}</h3>
                </div>
                <Button type="button" variant="outline" size="sm" onClick={onOpen}>
                    {actionLabel}
                </Button>
            </div>
            <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <dt className="text-muted-foreground">{t('common.kpis.unidades')}</dt>
                    <dd className="font-medium tabular-nums">{formatNumber(slice.unidades, locale)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">{t('common.kpis.ventas')}</dt>
                    <dd className="font-medium tabular-nums">{formatNumber(slice.ventas, locale, 0)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">{t('common.kpis.ingresos')}</dt>
                    <dd className="font-medium tabular-nums">
                        {formatMoney(slice.ingresos, moneda, locale)}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">{t('common.kpis.costo')}</dt>
                    <dd className="font-medium tabular-nums">{formatMoney(slice.costo, moneda, locale)}</dd>
                </div>
                <div className="col-span-2">
                    <dt className="text-muted-foreground">{t('common.kpis.utilidad')}</dt>
                    <dd
                        className={cn(
                            'font-semibold tabular-nums',
                            slice.utilidad !== null && slice.utilidad < 0
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-emerald-700 dark:text-emerald-400',
                        )}
                    >
                        {slice.utilidad === null
                            ? t('common.na')
                            : `${formatMoney(slice.utilidad, moneda, locale)} (${formatPct(slice.margen_pct, locale)})`}
                    </dd>
                </div>
            </dl>
        </div>
    );
}

export default function VentasServiciosIndex({
    moneda,
    filtros,
    totales,
    resumen,
    items,
    capabilities,
}: Props) {
    const { t, i18n } = useTranslation('reportes-ventas');
    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';
    const [search, setSearch] = useState('');

    const tipoActual = (filtros.tipo ?? 'todos') as Exclude<TabValue, 'resumen'>;
    const [tab, setTab] = useState<TabValue>(tipoActual === 'todos' ? 'resumen' : tipoActual);

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('common.reportes'), href: '#' },
                { title: t('servicios.title'), href: '/reportes/ventas-servicios' },
            ],
        });

        return () => {
            resetLayoutProps();
        };
    }, [t]);

    useEffect(() => {
        if (tipoActual !== 'todos') {
            setTab(tipoActual);
        }
    }, [tipoActual]);

    const navigateTipo = (tipo: Exclude<TabValue, 'resumen'>) => {
        setTab(tipo);
        router.get(
            '/reportes/ventas-servicios',
            {
                fecha_desde: filtros.fecha_desde,
                fecha_hasta: filtros.fecha_hasta,
                tipo,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const onTabChange = (value: string) => {
        const next = value as TabValue;
        if (next === 'resumen') {
            setTab('resumen');
            if (tipoActual !== 'todos') {
                router.get(
                    '/reportes/ventas-servicios',
                    {
                        fecha_desde: filtros.fecha_desde,
                        fecha_hasta: filtros.fecha_hasta,
                        tipo: 'todos',
                    },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        replace: true,
                    },
                );
            }

            return;
        }

        navigateTipo(next);
    };

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) {
            return items;
        }

        return items.filter((item) => {
            const haystack = `${item.nombre} ${item.categoria ?? ''} ${item.tipo}`.toLowerCase();

            return haystack.includes(q);
        });
    }, [items, search]);

    return (
        <>
            <Head title={t('servicios.title')} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader title={t('servicios.title')} description={t('servicios.description')} />

                <ReporteVentasFilters
                    url="/reportes/ventas-servicios"
                    filtros={filtros}
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder={t('servicios.search_placeholder')}
                    extraQuery={{ tipo: tipoActual }}
                />

                <Tabs value={tab} onValueChange={onTabChange}>
                    <TabsList className="flex h-auto w-full flex-wrap justify-start gap-1">
                        <TabsTrigger value="resumen">{t('servicios.tabs.resumen')}</TabsTrigger>
                        <TabsTrigger value="todos">{t('servicios.tabs.todos')}</TabsTrigger>
                        <TabsTrigger value="tratamiento">{t('servicios.tabs.tratamiento')}</TabsTrigger>
                        <TabsTrigger value="vacuna">{t('servicios.tabs.vacuna')}</TabsTrigger>
                        {capabilities.grooming ? (
                            <TabsTrigger value="grooming">{t('servicios.tabs.grooming')}</TabsTrigger>
                        ) : null}
                    </TabsList>

                    <TabsContent value="resumen" className="mt-4 space-y-4">
                        <p className="text-sm text-muted-foreground">{t('servicios.resumen_hint')}</p>
                        <div className="grid gap-4 lg:grid-cols-3">
                            <ResumenCard
                                title={t('servicios.tabs.tratamiento')}
                                icon={Stethoscope}
                                slice={resumen.tratamiento}
                                moneda={moneda}
                                locale={locale}
                                tone="text-sky-600 dark:text-sky-400"
                                onOpen={() => navigateTipo('tratamiento')}
                                actionLabel={t('servicios.ver_detalle')}
                            />
                            <ResumenCard
                                title={t('servicios.tabs.vacuna')}
                                icon={Syringe}
                                slice={resumen.vacuna}
                                moneda={moneda}
                                locale={locale}
                                tone="text-violet-600 dark:text-violet-400"
                                onOpen={() => navigateTipo('vacuna')}
                                actionLabel={t('servicios.ver_detalle')}
                            />
                            {capabilities.grooming ? (
                                <ResumenCard
                                    title={t('servicios.tabs.grooming')}
                                    icon={Scissors}
                                    slice={resumen.grooming}
                                    moneda={moneda}
                                    locale={locale}
                                    tone="text-teal-600 dark:text-teal-400"
                                    onOpen={() => navigateTipo('grooming')}
                                    actionLabel={t('servicios.ver_detalle')}
                                />
                            ) : null}
                        </div>
                    </TabsContent>

                    {(['todos', 'tratamiento', 'vacuna', 'grooming'] as const).map((value) => {
                        if (value === 'grooming' && !capabilities.grooming) {
                            return null;
                        }

                        return (
                            <TabsContent key={value} value={value} className="mt-4 space-y-4">
                                <ReporteVentasKpis totales={totales} moneda={moneda} locale={locale} />

                                {totales.items_sin_costo > 0 ? (
                                    <Alert className="border-amber-500/40 bg-amber-500/5">
                                        <AlertTriangle className="size-4 text-amber-600" />
                                        <AlertDescription>
                                            {t('common.sin_costo_aviso', {
                                                count: totales.items_sin_costo,
                                            })}
                                        </AlertDescription>
                                    </Alert>
                                ) : null}

                                <ReporteVentasTable
                                    items={filtered}
                                    moneda={moneda}
                                    locale={locale}
                                    showTipo={value === 'todos'}
                                    emptyLabel={t('common.sin_datos')}
                                />
                            </TabsContent>
                        );
                    })}
                </Tabs>
            </div>
        </>
    );
}
