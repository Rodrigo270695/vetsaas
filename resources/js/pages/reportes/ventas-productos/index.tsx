import { Head, setLayoutProps, resetLayoutProps } from '@inertiajs/react';
import { AlertTriangle, Download, Package } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/data-page';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { ReporteVentasFilters } from '@/pages/reportes/components/reporte-ventas-filters';
import { ReporteVentasKpis } from '@/pages/reportes/components/reporte-ventas-kpis';
import { ReporteVentasTable } from '@/pages/reportes/components/reporte-ventas-table';
import type {
    ReporteVentasFiltros,
    ReporteVentasItem,
    ReporteVentasTotales,
} from '@/pages/reportes/components/types';

type Props = {
    moneda: string;
    filtros: ReporteVentasFiltros;
    totales: ReporteVentasTotales;
    items: ReporteVentasItem[];
    can_export?: boolean;
};

export default function VentasProductosIndex({
    moneda,
    filtros,
    totales,
    items,
    can_export,
}: Props) {
    const { t, i18n } = useTranslation(['reportes-ventas', 'common']);
    const { can } = usePermission();
    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';
    const [search, setSearch] = useState('');
    const canExport = can_export ?? can('reporte-financiero.export');

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: t('common.reportes'), href: '#' },
                { title: t('productos.title'), href: '/reportes/ventas-productos' },
            ],
        });

        return () => {
            resetLayoutProps();
        };
    }, [t]);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) {
            return items;
        }

        return items.filter((item) => {
            const haystack = `${item.nombre} ${item.categoria ?? ''}`.toLowerCase();

            return haystack.includes(q);
        });
    }, [items, search]);

    const exportUrl = useMemo(() => {
        const params = new URLSearchParams();
        params.set('fecha_desde', filtros.fecha_desde);
        params.set('fecha_hasta', filtros.fecha_hasta);
        if (search.trim()) {
            params.set('search', search.trim());
        }

        return `/reportes/ventas-productos/export?${params.toString()}`;
    }, [filtros.fecha_desde, filtros.fecha_hasta, search]);

    return (
        <>
            <Head title={t('productos.title')} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('productos.title')}
                    description={
                        <span className="inline-flex items-center gap-2">
                            <Package className="size-4 text-emerald-600 dark:text-emerald-400" />
                            {t('productos.description')}
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
                    url="/reportes/ventas-productos"
                    filtros={filtros}
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder={t('productos.search_placeholder')}
                />

                <ReporteVentasKpis totales={totales} moneda={moneda} locale={locale} />

                {totales.items_sin_costo > 0 ? (
                    <Alert className="border-amber-500/40 bg-amber-500/5">
                        <AlertTriangle className="size-4 text-amber-600" />
                        <AlertDescription>
                            {t('common.sin_costo_aviso', { count: totales.items_sin_costo })}
                        </AlertDescription>
                    </Alert>
                ) : null}

                <ReporteVentasTable
                    items={filtered}
                    moneda={moneda}
                    locale={locale}
                    emptyLabel={t('common.sin_datos')}
                />
            </div>
        </>
    );
}
