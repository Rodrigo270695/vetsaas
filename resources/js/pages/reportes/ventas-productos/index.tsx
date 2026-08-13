import { Head, setLayoutProps, resetLayoutProps } from '@inertiajs/react';
import { AlertTriangle, Package } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { PageHeader } from '@/components/data-page';
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
};

export default function VentasProductosIndex({ moneda, filtros, totales, items }: Props) {
    const { t, i18n } = useTranslation('reportes-ventas');
    const locale = i18n.language?.startsWith('en') ? 'en-US' : 'es-PE';
    const [search, setSearch] = useState('');

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
